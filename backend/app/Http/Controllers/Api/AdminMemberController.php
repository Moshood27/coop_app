<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Scheme;
use App\Models\Contribution;
use App\Models\QardHasan;
use App\Models\LoanRepayment;
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
    protected $passbookService;

    public function __construct(PassbookService $passbookService)
    {
        $this->passbookService = $passbookService;
    }

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
            'total_savings' => $user->ordinary_savings, // or however savings is stored
            'total_shares' => $user->shares_capital,
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

        $passbookData = $this->passbookService->getPassbookData($user, $year);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'membership_number' => $user->membership_number,
            ],
            'year' => $year,
            'matrix' => $passbookData['matrix'],
            'month_labels' => $passbookData['month_labels'],
            'grand_total' => $passbookData['grand_total'],
            'bf_total' => $passbookData['bf_total'],
        ]);
    }

    /**
     * Distribute funds to a member's passbook (Manual Contribution).
     */
    public function distributeFunds(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $data = $request->validate([
            'scheme_id' => 'required_without:split_50_50|nullable|exists:schemes,id',
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'required|date',
            'method' => 'required|string|in:cash,transfer,pos,other',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
            'split_50_50' => 'nullable|boolean',
        ]);

        $contributions = [];

        if (!empty($data['split_50_50'])) {
            $halfAmount = $data['amount'] / 2;

            // Find Savings and Shares schemes
            $savingsScheme = Scheme::where('name', 'like', '%Savings%')->first();
            $sharesScheme = Scheme::where('name', 'like', '%Share%')->first();

            if (!$savingsScheme || !$sharesScheme) {
                return response()->json(['message' => 'Savings or Shares scheme not found for split.'], 422);
            }

            foreach ([$savingsScheme, $sharesScheme] as $scheme) {
                $con = $user->contributions()->create([
                    'scheme_id' => $scheme->id,
                    'amount' => $halfAmount,
                    'status' => 'success',
                    'paid_at' => Carbon::parse($data['paid_at']),
                    'payment_method' => $data['method'],
                    'reference' => ($data['reference'] ?? ('SPL-'.strtoupper(Str::random(8)))) . '-' . strtoupper(substr($scheme->name, 0, 3)),
                    'notes' => $data['note'] . " (Split 50/50)",
                    'metadata' => [
                        'admin_id' => $request->user()->id,
                        'type' => 'manual_distribution_split'
                    ]
                ]);
                $user->syncSchemeBalance($scheme->name);
                $contributions[] = $con;
            }
        } else {
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
            $contributions[] = $contribution;
        }

        return response()->json([
            'message' => 'Funds distributed successfully.',
            'contributions' => $contributions
        ]);
    }

    /**
     * Update an existing contribution.
     */
    public function updateContribution(Request $request, Contribution $contribution)
    {
        $this->authorizeAdminAccess($request->user(), $contribution->user);

        $data = $request->validate([
            'scheme_id' => 'required|exists:schemes,id',
            'amount' => 'required|numeric|min:0',
            'paid_at' => 'required|date',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string|max:255',
            'status' => 'required|string|in:pending,success,failed',
        ]);

        $oldScheme = $contribution->scheme;
        $contribution->update($data);

        // Sync balances
        if ($oldScheme) $contribution->user->syncSchemeBalance($oldScheme->name);
        $newScheme = Scheme::find($data['scheme_id']);
        if ($newScheme && (!$oldScheme || $newScheme->id !== $oldScheme->id)) {
            $contribution->user->syncSchemeBalance($newScheme->name);
        }

        return response()->json(['message' => 'Contribution updated successfully.', 'contribution' => $contribution]);
    }

    /**
     * Delete a contribution.
     */
    public function deleteContribution(Request $request, Contribution $contribution)
    {
        $this->authorizeAdminAccess($request->user(), $contribution->user);

        $user = $contribution->user;
        $schemeName = $contribution->scheme?->name;

        $contribution->delete();

        if ($schemeName) {
            $user->syncSchemeBalance($schemeName);
        }

        return response()->json(['message' => 'Contribution deleted successfully.']);
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
     * Update a loan.
     */
    public function updateLoan(Request $request, QardHasan $loan)
    {
        $this->authorizeAdminAccess($request->user(), $loan->user);

        $data = $request->validate([
            'principal_amount' => 'required|numeric|min:0',
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $loan->update($data);

        return response()->json(['message' => 'Loan updated successfully.', 'loan' => $loan]);
    }

    /**
     * Delete a loan.
     */
    public function deleteLoan(Request $request, QardHasan $loan)
    {
        $this->authorizeAdminAccess($request->user(), $loan->user);

        $loan->delete();

        return response()->json(['message' => 'Loan deleted successfully.']);
    }

    /**
     * Update a loan repayment.
     */
    public function updateLoanRepayment(Request $request, LoanRepayment $repayment)
    {
        $this->authorizeAdminAccess($request->user(), $repayment->qardHasan->user);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'paid_at' => 'required|date',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $loan = $repayment->qardHasan;
        $repayment->update($data);

        // Re-sync loan paid amount
        $loan->update([
            'paid_amount' => $loan->repayments()->sum('amount')
        ]);

        // Auto-complete loan if fully paid
        if ($loan->paid_amount >= $loan->principal_amount && $loan->status !== 'completed') {
            $loan->update(['status' => 'completed']);
        }

        return response()->json(['message' => 'Repayment updated successfully.', 'repayment' => $repayment]);
    }

    /**
     * Delete a loan repayment.
     */
    public function deleteLoanRepayment(Request $request, LoanRepayment $repayment)
    {
        $this->authorizeAdminAccess($request->user(), $repayment->qardHasan->user);

        $loan = $repayment->qardHasan;
        $repayment->delete();

        // Re-sync loan paid amount
        $loan->update([
            'paid_amount' => $loan->repayments()->sum('amount')
        ]);

        if ($loan->paid_amount < $loan->principal_amount && $loan->status === 'completed') {
            $loan->update(['status' => 'active']);
        }

        return response()->json(['message' => 'Repayment deleted successfully.']);
    }

    /**
     * Get recent contributions for a member.
     */
    public function contributions(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $contributions = $user->contributions()
            ->with('scheme')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($contributions);
    }

    /**
     * Get recent wallet transactions for a member.
     */
    public function walletTransactions(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $transactions = $user->walletTransactions()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($transactions);
    }

    /**
     * Update a wallet transaction.
     */
    public function updateWalletTransaction(Request $request, WalletTransaction $transaction)
    {
        $this->authorizeAdminAccess($request->user(), $transaction->user);

        $data = $request->validate([
            'amount' => 'required|numeric',
            'type' => 'required|string|in:credit,debit',
            'status' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $transaction->update($data);

        // Note: Wallet balance might need re-syncing if we change amount/type,
        // but usually we don't allow deep edits of ledger-like records without careful sync.
        // For now, simple update.

        return response()->json(['message' => 'Transaction updated successfully.', 'transaction' => $transaction]);
    }

    /**
     * Delete a wallet transaction.
     */
    public function deleteWalletTransaction(Request $request, WalletTransaction $transaction)
    {
        $this->authorizeAdminAccess($request->user(), $transaction->user);
        $transaction->delete();
        return response()->json(['message' => 'Transaction deleted successfully.']);
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
