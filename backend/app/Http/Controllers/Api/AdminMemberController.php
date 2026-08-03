<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Scheme;
use App\Models\Contribution;
use App\Models\QardHasan;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\AdministrativeChargeService;
use App\Services\PassbookService;
use Illuminate\Support\Facades\Log;

class AdminMemberController extends Controller
{
    /**
     * List members, optionally filtered by branch if the admin is branch-bound.
     */
    public function index(Request $request)
    {
        $admin = $request->user();
        $query = User::query();

        // Enforce "super admin that belongs to specific branch" or global super admin
        if ($admin->branch_id) {
            $query->where('branch_id', $admin->branch_id);
        } elseif (!$admin->hasRole('super_admin')) {
            abort(403, 'Unauthorized.');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('surname', 'like', "%{$search}%")
                  ->orWhere('other_names', 'like', "%{$search}%")
                  ->orWhere('membership_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $members = $query->with('branch')
            ->orderBy('name')
            ->paginate(20);

        return response()->json($members);
    }

    /**
     * Get member details.
     */
    public function show(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $user->load(['branch', 'roles']);

        return response()->json([
            'user' => $user,
            'balance' => $user->balance,
            'total_balance' => $user->getTotalBalance(),
            'outstanding_loans' => $user->qardHasans()->whereIn('status', ['active', 'defaulted'])->sum(DB::raw('principal_amount - paid_amount')),
        ]);
    }

    /**
     * Get passbook matrix for a member.
     */
    public function passbook(Request $request, User $user, int $year)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $startOfYear = Carbon::create($year, 1, 1, 0, 0, 0);

        $yearContributions = $user->contributions()
            ->where(function($query) use ($year) {
                $query->whereYear('paid_at', $year)
                      ->orWhere(function($q) use ($year) {
                          $q->whereNull('paid_at')->whereYear('created_at', $year);
                      });
            })
            ->where('status', 'success')
            ->get();

        $bfContributions = $user->contributions()
            ->where(function($query) use ($startOfYear) {
                $query->where('paid_at', '<', $startOfYear)
                      ->orWhere(function($q) use ($startOfYear) {
                          $q->whereNull('paid_at')->where('created_at', '<', $startOfYear);
                      });
            })
            ->where('status', 'success')
            ->get();

        $userSchemeIds = $user->contributions()->where('status', 'success')->distinct()->pluck('scheme_id');
        $schemes = Scheme::where('active', true)->orWhereIn('id', $userSchemeIds)->orderBy('name')->get();

        $matrix = $schemes->map(function ($scheme) use ($yearContributions, $bfContributions) {
            $row = [
                'scheme_id' => $scheme->id,
                'scheme_name' => $scheme->name,
                'months' => array_fill(1, 12, 0),
                'bf' => 0.0,
                'total' => 0.0,
            ];

            foreach ($bfContributions as $con) {
                if ($con->scheme_id == $scheme->id) {
                    $row['bf'] += (float) $con->amount;
                }
            }

            $row['total'] = $row['bf'];

            foreach ($yearContributions as $con) {
                if ($con->scheme_id == $scheme->id) {
                    $date = $con->paid_at ?? $con->created_at;
                    $month = $date->month;
                    $row['months'][$month] += (float) $con->amount;
                    $row['total'] += (float) $con->amount;
                }
            }

            return $row;
        });

        return response()->json([
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'membership_number' => $user->membership_number,
            ],
            'year' => $year,
            'matrix' => $matrix,
            'grand_total' => $matrix->sum('total'),
            'bf_total' => $matrix->sum('bf'),
        ]);
    }

    /**
     * Distribute funds to a member's passbook (Manual Contribution).
     */
    public function distributeFunds(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $data = $request->validate([
            'scheme_id' => 'required|exists:schemes,id',
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'required|date',
            'method' => 'required|string|in:cash,transfer,pos,other',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
        ]);

        $contribution = $user->contributions()->create([
            'scheme_id' => $data['scheme_id'],
            'amount' => $data['amount'],
            'status' => 'success',
            'paid_at' => Carbon::parse($data['paid_at']),
            'payment_method' => $data['method'],
            'reference' => $data['reference'] ?? ('MAN-'.strtoupper(Str::random(10))),
            'notes' => $data['note'],
            'metadata' => [
                'admin_id' => $request->user()->id,
                'type' => 'manual_distribution'
            ]
        ]);

        // Sync scheme balance
        $scheme = Scheme::find($data['scheme_id']);
        $user->syncSchemeBalance($scheme->name);

        return response()->json([
            'message' => 'Funds distributed successfully.',
            'contribution' => $contribution
        ]);
    }

    /**
     * Manage member wallet allocation.
     */
    public function allocateWallet(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $data = $request->validate([
            'allocations' => 'required|array',
            'allocations.*.scheme_id' => 'required|exists:schemes,id',
            'allocations.*.amount' => 'required|numeric|min:0',
        ]);

        $totalRequested = collect($data['allocations'])->sum('amount');

        if ($user->balance < $totalRequested) {
            return response()->json(['message' => 'Insufficient wallet balance.'], 422);
        }

        DB::transaction(function () use ($user, $data, $request) {
            foreach ($data['allocations'] as $alloc) {
                if ($alloc['amount'] <= 0) continue;

                $scheme = Scheme::find($alloc['scheme_id']);

                // 1. Deduct from wallet
                $user->decrement('balance', $alloc['amount']);

                // 2. Create wallet transaction record
                $user->walletTransactions()->create([
                    'amount' => $alloc['amount'],
                    'type' => 'debit',
                    'action' => 'allocation',
                    'status' => 'success',
                    'description' => "Allocation to {$scheme->name} (by Admin)",
                    'metadata' => ['admin_id' => $request->user()->id]
                ]);

                // 3. Create contribution record
                $user->contributions()->create([
                    'scheme_id' => $scheme->id,
                    'amount' => $alloc['amount'],
                    'status' => 'success',
                    'paid_at' => now(),
                    'payment_method' => 'wallet',
                    'reference' => 'ALC-'.strtoupper(Str::random(10)),
                    'notes' => 'Allocated from wallet by Admin',
                ]);

                // 4. Sync scheme balance
                $user->syncSchemeBalance($scheme->name);
            }
        });

        return response()->json(['message' => 'Wallet funds allocated successfully.']);
    }

    /**
     * List member loans.
     */
    public function loans(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $loans = $user->qardHasans()
            ->with(['repayments'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($loans);
    }

    /**
     * Record loan repayment manually.
     */
    public function loanRepayment(Request $request, QardHasan $loan)
    {
        $this->authorizeAdminAccess($request->user(), $loan->user);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|in:cash,transfer,pos,wallet,other',
            'paid_at' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $amount = (float) $data['amount'];

        DB::transaction(function () use ($loan, $amount, $data, $request) {
            if ($data['method'] === 'wallet') {
                if ($loan->user->balance < $amount) {
                    throw new \Exception('Insufficient wallet balance.');
                }
                $loan->user->decrement('balance', $amount);
                $loan->user->walletTransactions()->create([
                    'amount' => $amount,
                    'type' => 'debit',
                    'action' => 'loan_repayment',
                    'status' => 'success',
                    'description' => "Loan Repayment for QH-{$loan->id} (by Admin)",
                    'metadata' => ['admin_id' => $request->user()->id]
                ]);
            }

            $loan->repayments()->create([
                'amount' => $amount,
                'payment_method' => $data['method'],
                'paid_at' => Carbon::parse($data['paid_at']),
                'notes' => $data['note'],
                'status' => 'success',
            ]);

            $loan->increment('paid_amount', $amount);

            if ($loan->paid_amount >= $loan->principal_amount) {
                $loan->update(['status' => 'completed', 'completed_at' => now()]);
            }
        });

        return response()->json(['message' => 'Loan repayment recorded successfully.']);
    }

    /**
     * Authorize admin access to member data.
     */
    protected function authorizeAdminAccess(User $admin, User $member)
    {
        if ($admin->hasRole('super_admin') && !$admin->branch_id) {
            return true; // Global super admin
        }

        if ($admin->hasRole('super_admin') && $admin->branch_id === $member->branch_id) {
            return true; // Branch-bound super admin
        }

        abort(403, 'Unauthorized access to this member.');
    }
}
