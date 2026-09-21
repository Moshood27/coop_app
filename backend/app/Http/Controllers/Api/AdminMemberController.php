<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Scheme;
use App\Models\Contribution;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\WalletTransaction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\AdministrativeChargeService;
use App\Services\PassbookService;
use App\Services\AttendanceService;
use App\Services\PaystackService;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use Illuminate\Validation\Rule;

class AdminMemberController extends Controller
{
    /**
     * List members, optionally filtered by branch if the admin is branch-bound.
     */
    protected $passbookService;
    protected $attendanceService;

    public function __construct(PassbookService $passbookService, AttendanceService $attendanceService)
    {
        $this->passbookService = $passbookService;
        $this->attendanceService = $attendanceService;
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

        $search = $request->input('q') ?? $request->input('search');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('surname', 'like', "%{$search}%")
                  ->orWhere('other_names', 'like', "%{$search}%")
                  ->orWhere('membership_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(surname, ' ', name)"), 'like', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(name, ' ', surname)"), 'like', "%{$search}%");
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
     * Update member profile.
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $data = $request->validate([
            'surname' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'other_names' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'membership_number' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user->id)],
            'residential_address' => ['nullable', 'string'],
            'gender' => ['required', 'string', Rule::in(['male', 'female', 'other'])],
            'branch_id' => ['required', 'exists:branches,id'],
            'password' => ['nullable', 'string'],
        ]);

        if (!empty($data['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($data['password']);
        }

        $user->fill(collect($data)->except('password')->toArray());
        $user->save();

        return response()->json([
            'message' => 'Member profile updated successfully.',
            'user' => $user->fresh(['branch', 'roles']),
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
                'passport_url' => $user->passport_url,
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
            'amount' => 'required|numeric',
            'paid_at' => 'required|date',
            'method' => 'required|string|in:cash,transfer,pos,other',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
            'split_50_50' => 'nullable|boolean',
        ]);

        $contributions = [];

        DB::transaction(function () use ($user, $data, $request, &$contributions) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            if (!empty($data['split_50_50'])) {
                $halfAmount = $data['amount'] / 2;

                // Find Savings and Shares schemes
                $savingsScheme = Scheme::where('name', 'Ordinary Savings')
                    ->orWhere('name', 'Savings')
                    ->first();
                $sharesScheme = Scheme::where('name', 'Shares')
                    ->orWhere('name', 'Share Capital')
                    ->first();

                if (!$savingsScheme || !$sharesScheme) {
                    throw new \Exception('Savings or Shares scheme not found for split.');
                }

                foreach ([$savingsScheme, $sharesScheme] as $scheme) {
                    $con = $lockedUser->contributions()->create([
                        'scheme_id' => $scheme->id,
                        'amount' => $halfAmount,
                        'status' => 'success',
                        'paid_at' => Carbon::parse($data['paid_at']),
                        'payment_method' => $data['method'],
                        'reference' => ($data['reference'] ?? ('SPL-'.strtoupper(Str::random(8)))) . '-' . strtoupper(substr($scheme->name, 0, 3)),
                        'notes' => ($data['notes'] ?? '') . " (Split 50/50)",
                        'metadata' => [
                            'admin_id' => $request->user()->id,
                            'type' => 'manual_distribution_split'
                        ]
                    ]);
                    $lockedUser->syncSchemeBalance($scheme->name);
                    $contributions[] = $con;
                }
            } else {
                $contribution = $lockedUser->contributions()->create([
                    'scheme_id' => $data['scheme_id'],
                    'amount' => $data['amount'],
                    'status' => 'success',
                    'paid_at' => Carbon::parse($data['paid_at']),
                    'payment_method' => $data['method'],
                    'reference' => $data['reference'] ?? ('MAN-'.strtoupper(Str::random(10))),
                    'notes' => $data['notes'] ?? null,
                    'metadata' => [
                        'admin_id' => $request->user()->id,
                        'type' => 'manual_distribution'
                    ]
                ]);

                // Sync scheme balance
                $scheme = Scheme::find($data['scheme_id']);
                $lockedUser->syncSchemeBalance($scheme->name);
                $contributions[] = $contribution;
            }
        });

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
            'amount' => 'required|numeric',
            'paid_at' => 'required|date',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string|max:255',
            'status' => 'required|string|in:pending,success,failed',
        ]);

        DB::transaction(function () use ($contribution, $data) {
            $lockedUser = User::where('id', $contribution->user_id)->lockForUpdate()->first();
            $oldScheme = $contribution->scheme;
            $contribution->update($data);

            // Sync balances
            if ($oldScheme) $lockedUser->syncSchemeBalance($oldScheme->name);
            $newScheme = Scheme::find($data['scheme_id']);
            if ($newScheme && (!$oldScheme || $newScheme->id !== $oldScheme->id)) {
                $lockedUser->syncSchemeBalance($newScheme->name);
            }
        });

        return response()->json(['message' => 'Contribution updated successfully.', 'contribution' => $contribution->fresh()]);
    }

    /**
     * Delete a contribution.
     */
    public function deleteContribution(Request $request, Contribution $contribution)
    {
        $this->authorizeAdminAccess($request->user(), $contribution->user);

        DB::transaction(function () use ($contribution) {
            $lockedUser = User::where('id', $contribution->user_id)->lockForUpdate()->first();
            $schemeName = $contribution->scheme?->name;

            $contribution->delete();

            if ($schemeName) {
                $lockedUser->syncSchemeBalance($schemeName);
            }
        });

        return response()->json(['message' => 'Contribution deleted successfully.']);
    }

    /**
     * Allocate funds from Admin's wallet to a member's schemes (passbook).
     */
    public function allocateFromAdminWallet(Request $request, User $user)
    {
        if (!Setting::get('admin_allocation_enabled', true)) {
            return response()->json(['message' => 'Admin allocation is currently disabled.'], 403);
        }

        $admin = $request->user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'allocations' => 'required|array',
            'allocations.*.scheme_id' => 'required|exists:schemes,id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255',
        ]);

        $totalRequested = collect($data['allocations'])->sum('amount');
        $notes = $data['notes'] ?? 'Allocation from Admin Wallet';

        if ($admin->balance < $totalRequested) {
            return response()->json(['message' => 'Insufficient admin wallet balance.'], 422);
        }

        $reference = 'ADMIN_ALLOC_' . now()->format('YmdHis') . '_' . $user->id . '_' . bin2hex(random_bytes(3));

        DB::transaction(function () use ($admin, $user, $data, $reference, $totalRequested, $notes) {
            $lockedAdmin = User::where('id', $admin->id)->lockForUpdate()->first();
            $lockedMember = User::where('id', $user->id)->lockForUpdate()->first();

            if ($lockedAdmin->balance < $totalRequested) {
                throw new \Exception('Insufficient admin wallet balance.');
            }

            // 1. Deduct full requested amount from Admin's wallet
            $lockedAdmin->decrement('balance', $totalRequested);

            // Record Debit for Admin
            $adminItems = [];
            foreach ($data['allocations'] as $alloc) {
                $scheme = Scheme::find($alloc['scheme_id']);
                $adminItems[] = [
                    'scheme_id' => $scheme->id,
                    'scheme_name' => $scheme->name,
                    'amount' => $alloc['amount'],
                ];
            }

            $lockedAdmin->walletTransactions()->create([
                'amount' => $totalRequested,
                'type' => 'debit',
                'reference' => $reference . '_DR',
                'source' => 'admin_allocation_debit',
                'meta' => [
                    'member_id' => $user->id,
                    'member_name' => $user->full_name,
                    'description' => "Allocation to Member: {$user->full_name}",
                    'notes' => $notes,
                    'distribution' => $adminItems,
                ]
            ]);

            // 2. Credit GROSS amount to Member wallet (representing the inflow)
            $lockedMember->increment('balance', $totalRequested);
            $lockedMember->walletTransactions()->create([
                'amount' => $totalRequested,
                'type' => 'credit',
                'reference' => $reference . '_GROSS',
                'source' => 'admin_allocation_credit',
                'meta' => [
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->full_name,
                    'description' => "Gross allocation from Admin: {$admin->full_name}",
                    'notes' => $notes,
                ]
            ]);

            // 3. Apply deductions (Fines + Admin Charges) from Member's wallet
            $chargeService = app(\App\Services\AdministrativeChargeService::class);

            $exclude = [];
            foreach ($data['allocations'] as $alloc) {
                $scheme = Scheme::find($alloc['scheme_id']);
                if ($scheme) {
                    if (strtoupper($scheme->name) === 'SITTING') $exclude[] = 'SITTING';
                    if (strtoupper($scheme->name) === 'FINE') $exclude[] = 'FINE';
                }
            }

            $deductionResult = $chargeService->applyDeductionsFromWallet($lockedMember, $totalRequested, false, $reference, $exclude);
            $remainingToAllocate = $deductionResult['net_amount'];
            $totalDeducted = $totalRequested - $remainingToAllocate;

            // 4. Allocate remaining funds to member's schemes
            $actualAllocations = [];
            foreach ($data['allocations'] as $alloc) {
                if ($remainingToAllocate <= 0) break;

                $scheme = Scheme::find($alloc['scheme_id']);
                $applied = min($remainingToAllocate, (float)$alloc['amount']);

                $lockedMember->contributions()->create([
                    'scheme_id' => $scheme->id,
                    'amount' => $applied,
                    'status' => 'success',
                    'paid_at' => now(),
                    'payment_method' => 'admin_wallet',
                    'reference' => $reference . '_' . $scheme->id,
                    'notes' => $notes . ($totalDeducted > 0 ? " (Net after deductions)" : ""),
                ]);

                $lockedMember->syncSchemeBalance($scheme->name);

                // Debit the wallet for the amount allocated to scheme
                $lockedMember->decrement('balance', $applied);
                WalletTransaction::create([
                    'user_id' => $lockedMember->id,
                    'type' => 'debit',
                    'amount' => $applied,
                    'reference' => $reference . '_ALC_' . $scheme->id,
                    'source' => 'scheme_allocation',
                    'meta' => ['scheme_id' => $scheme->id, 'scheme_name' => $scheme->name, 'notes' => $notes]
                ]);

                $remainingToAllocate -= $applied;
                $actualAllocations[] = ['scheme_id' => $scheme->id, 'scheme_name' => $scheme->name, 'amount' => $applied];
            }

            // 5. Notify Member
            $lockedMember->notifyMember(
                'Funds Allocated by Admin',
                "An administrator has allocated ₦" . number_format($totalRequested, 2) . " to your account. Gross: ₦" . number_format($totalRequested, 2) . ". Net after mandatory deductions: ₦" . number_format($deductionResult['net_amount'], 2) . ".",
                [
                    'type' => 'admin_allocation',
                    'amount' => (float) $totalRequested,
                    'net_amount' => (float) $deductionResult['net_amount'],
                    'deductions' => $deductionResult['deductions'],
                    'route' => '/passbook',
                    'action_text' => 'View Passbook'
                ]
            );
        });

        return response()->json(['message' => 'Funds allocated from admin wallet successfully.']);
    }

    /**
     * Assign a Paystack Virtual Account to a member (triggered by admin).
     */
    public function assignVirtualAccount(Request $request, User $user)
    {
        if (!Setting::get('admin_member_funding_enabled', true)) {
            return response()->json(['message' => 'Admin-initiated member funding is currently disabled.'], 403);
        }

        $this->authorizeAdminAccess($request->user(), $user);

        $validated = $request->validate([
            'preferred_bank' => 'nullable|string',
            'phone' => 'nullable|string',
            'bvn' => 'nullable|string|digits:11',
        ]);

        $bvn = $validated['bvn'] ?? $user->bvn;
        $phone = $validated['phone'] ?? $user->phone;

        if (empty($bvn)) return response()->json(['message' => 'Member BVN is required.'], 422);
        if (empty($phone)) return response()->json(['message' => 'Member phone number is required.'], 422);

        $paystack = app(PaystackService::class);

        // Sync Customer
        $sync = $paystack->syncCustomer($user, $phone);
        if (!$sync['success']) {
            return response()->json(['message' => $sync['message']], 502);
        }

        $customerCode = $sync['customer_code'];
        $paystackData = $sync['data'];
        $isIdentified = $paystackData['identified'] ?? false;

        // Identification
        if (!$isIdentified) {
            $ident = $paystack->submitIdentification($user, $customerCode, $bvn);
            if (!$ident['success']) {
                return response()->json(['message' => $ident['message']], 422);
            }
            sleep(3);
        }

        // Assign DVA
        $assign = $paystack->assignDva($user, $customerCode, $validated['preferred_bank'] ?? 'wema-bank');

        if ($assign['success']) {
            $user->update(['bvn' => $bvn]);
            return response()->json([
                'message' => 'Virtual account assigned successfully.',
                'virtual_account' => $user->fresh()->virtualAccount
            ]);
        }

        return response()->json(['message' => $assign['message']], 502);
    }

    /**
     * Initialize Paystack checkout for a member's wallet (triggered by admin).
     */
    public function initializeWalletFunding(Request $request, User $user)
    {
        if (!Setting::get('admin_member_funding_enabled', true)) {
            return response()->json(['message' => 'Admin-initiated member funding is currently disabled.'], 403);
        }

        $this->authorizeAdminAccess($request->user(), $user);

        $data = $request->validate([
            'amount' => 'required|numeric|min:100',
            'callback_url' => 'nullable|url',
        ]);

        $secret = config('services.paystack.secret_key');
        if (!$secret) {
            return response()->json(['message' => 'Payment provider not configured'], 500);
        }

        $reference = 'ADMIN_TOPUP_' . now()->format('YmdHis') . '_' . $user->id . '_' . bin2hex(random_bytes(3));

        $payload = [
            'email' => $user->email,
            'amount' => (int) round($data['amount'] * 100),
            'reference' => $reference,
            'currency' => 'NGN',
            'metadata' => [
                'user_id' => $user->id,
                'admin_id' => $request->user()->id,
                'type' => 'admin_initiated_topup',
            ],
        ];

        if ($request->filled('callback_url')) {
            $payload['callback_url'] = $data['callback_url'];
        }

        $response = Http::withToken($secret)
            ->acceptJson()
            ->post('https://api.paystack.co/transaction/initialize', $payload);

        if (!$response->ok() || !($response->json('status') === true)) {
            Log::error('Paystack Admin Wallet Initialize failed', ['user_id' => $user->id, 'body' => $response->json()]);
            return response()->json(['message' => 'Failed to initialize payment'], 502);
        }

        $resData = $response->json('data');
        return response()->json([
            'authorization_url' => $resData['authorization_url'],
            'reference' => $reference,
            'amount' => $data['amount'],
        ]);
    }

    /**
     * Initialize Paystack checkout for specific scheme payments (triggered by admin).
     */
    public function initializeSchemeFunding(Request $request, User $user)
    {
        if (!Setting::get('admin_member_funding_enabled', true)) {
            return response()->json(['message' => 'Admin-initiated member funding is currently disabled.'], 403);
        }

        $this->authorizeAdminAccess($request->user(), $user);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.scheme_id' => 'required|exists:schemes,id',
            'items.*.amount' => 'required|numeric|min:1',
            'callback_url' => 'nullable|url',
        ]);

        $secret = config('services.paystack.secret_key');
        if (!$secret) {
            return response()->json(['message' => 'Payment provider not configured'], 500);
        }

        $reference = 'ADMIN_SCHEME_PAY_' . now()->format('YmdHis') . '_' . $user->id . '_' . bin2hex(random_bytes(3));
        $totalAmount = collect($validated['items'])->sum('amount');

        // Pre-create pending contributions for member
        foreach ($validated['items'] as $item) {
            $user->contributions()->create([
                'scheme_id' => $item['scheme_id'],
                'amount' => $item['amount'],
                'reference' => $reference,
                'status' => 'pending',
                'category' => 'deposit',
                'notes' => 'Admin-initiated scheme payment'
            ]);
        }

        $payload = [
            'email' => $user->email,
            'amount' => (int) round($totalAmount * 100), // Kobo
            'reference' => $reference,
            'currency' => 'NGN',
            'metadata' => [
                'user_id' => $user->id,
                'admin_id' => $request->user()->id,
                'type' => 'admin_initiated_scheme_payment',
                'distribution' => $validated['items']
            ],
        ];

        if ($request->filled('callback_url')) {
            $payload['callback_url'] = $validated['callback_url'];
        }

        $response = Http::withToken($secret)
            ->acceptJson()
            ->post('https://api.paystack.co/transaction/initialize', $payload);

        if (!$response->ok() || !($response->json('status') === true)) {
            Log::error('Paystack Admin Scheme Initialize failed', ['user_id' => $user->id, 'body' => $response->json()]);
            return response()->json(['message' => 'Failed to initialize payment'], 502);
        }

        $resData = $response->json('data');
        return response()->json([
            'authorization_url' => $resData['authorization_url'],
            'reference' => $reference,
            'total' => $totalAmount,
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
            'notes' => 'nullable|string|max:255',
        ]);

        $totalRequested = collect($data['allocations'])->sum('amount');
        $notes = $data['notes'] ?? 'Allocated from wallet by Admin';

        if ($user->balance < $totalRequested) {
            return response()->json(['message' => 'Insufficient wallet balance.'], 422);
        }

        $reference = 'WALLET_ALLOC_' . now()->format('YmdHis') . '_' . $user->id . '_' . bin2hex(random_bytes(3));

        DB::transaction(function () use ($user, $data, $request, $reference, $totalRequested, $notes) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            if ($lockedUser->balance < $totalRequested) {
                throw new \Exception('Insufficient wallet balance.');
            }

            $items = [];
            foreach ($data['allocations'] as $alloc) {
                if ($alloc['amount'] <= 0) continue;

                $scheme = Scheme::find($alloc['scheme_id']);

                // 1. Create contribution record
                $lockedUser->contributions()->create([
                    'scheme_id' => $scheme->id,
                    'amount' => $alloc['amount'],
                    'status' => 'success',
                    'paid_at' => now(),
                    'payment_method' => 'wallet',
                    'reference' => $reference,
                    'notes' => $notes,
                ]);

                // 2. Sync scheme balance
                $lockedUser->syncSchemeBalance($scheme->name);

                $items[] = [
                    'scheme_id' => $scheme->id,
                    'scheme_name' => $scheme->name,
                    'amount' => $alloc['amount'],
                    'category' => 'deposit',
                ];
            }

            // 3. Deduct from wallet total
            $lockedUser->decrement('balance', $totalRequested);

            // 4. Create wallet transaction record
            $lockedUser->walletTransactions()->create([
                'amount' => $totalRequested,
                'type' => 'debit',
                'reference' => $reference,
                'source' => 'wallet_allocation',
                'meta' => [
                    'admin_id' => $request->user()->id,
                    'description' => "Allocation to multiple schemes (by Admin)",
                    'notes' => $notes,
                    'distribution' => $items,
                ]
            ]);
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
            ->with(['repayments' => fn($q) => $q->orderByDesc('paid_at')])
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
            'description' => 'nullable|string',
            'repayment_start_date' => 'nullable|date',
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
    public function updateLoanRepayment(Request $request, QardHasanRepayment $repayment)
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
    public function deleteLoanRepayment(Request $request, QardHasanRepayment $repayment)
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
            'reference' => 'nullable|string|max:100',
            'source' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $meta = $transaction->meta ?? [];
        if (isset($data['description'])) $meta['notes'] = $data['description'];
        if (isset($data['status'])) $meta['status'] = $data['status'];

        $transaction->update([
            'amount' => $data['amount'],
            'type' => $data['type'],
            'reference' => $data['reference'] ?? $transaction->reference,
            'source' => $data['source'] ?? $transaction->source,
            'meta' => $meta,
        ]);

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
            'notes' => 'nullable|string|max:255',
        ]);

        $amount = round((float) $data['amount'], 2);

        DB::transaction(function () use ($loan, $amount, $data, $request) {
            $lockedUser = User::where('id', $loan->user_id)->lockForUpdate()->first();
            $lockedLoan = QardHasan::where('id', $loan->id)->lockForUpdate()->first();

            if ($data['method'] === 'wallet') {
                if ($lockedUser->balance < $amount) {
                    throw new \Exception('Insufficient wallet balance.');
                }
                $lockedUser->decrement('balance', $amount);
                $lockedUser->walletTransactions()->create([
                    'amount' => $amount,
                    'type' => 'debit',
                    'reference' => 'LRP-' . strtoupper(Str::random(12)),
                    'source' => 'loan_repayment',
                    'meta' => [
                        'loan_id' => $lockedLoan->id,
                        'admin_id' => $request->user()->id,
                        'description' => "Loan Repayment for QH-{$lockedLoan->id} (by Admin)",
                        'notes' => $data['notes'] ?? null
                    ]
                ]);
            }

            $lockedLoan->repayments()->create([
                'amount' => $amount,
                'payment_method' => $data['method'],
                'reference' => 'QH-REP-' . strtoupper(Str::random(12)),
                'paid_at' => Carbon::parse($data['paid_at']),
                'notes' => $data['notes'] ?? null,
                'status' => 'success',
            ]);

            $lockedLoan->increment('paid_amount', $amount);

            if ($lockedLoan->paid_amount >= $lockedLoan->principal_amount) {
                $lockedLoan->update(['status' => 'completed', 'completed_at' => now()]);
            }
        });

        return response()->json(['message' => 'Loan repayment recorded successfully.']);
    }

    /**
     * Create a new member profile.
     */
    public function store(Request $request)
    {
        $admin = $request->user();
        if (!$admin->hasRole('super_admin') && !$admin->branch_id) {
            abort(403, 'Unauthorized.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'other_names' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users')],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'branch_id' => ['required', 'exists:branches,id'],
            'password' => ['required', 'string'],
            'residential_address' => ['nullable', 'string'],
            'membership_number' => ['nullable', 'string', 'max:255', Rule::unique('users')],
        ]);

        // If admin is branch-bound, force the branch_id
        if ($admin->branch_id) {
            $data['branch_id'] = $admin->branch_id;
        }

        $membership = $data['membership_number'] ?? User::generateMembershipNumber((int) $data['branch_id']);

        $user = User::create([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'other_names' => $data['other_names'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'gender' => $data['gender'],
            'branch_id' => $data['branch_id'],
            'residential_address' => $data['residential_address'] ?? null,
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            'membership_number' => $membership,
            'is_admin' => false,
            'approval_status' => 'approved', // Admin created members are approved by default
        ]);

        return response()->json([
            'message' => 'Member created successfully.',
            'user' => $user,
        ], 201);
    }

    /**
     * Delete a member profile.
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        // Check if member has active loans or balance
        if ($user->hasActiveLoan()) {
            return response()->json(['message' => 'Cannot delete member with active loans.'], 422);
        }

        if ($user->getTotalBalance() > 0.01) {
            return response()->json(['message' => 'Cannot delete member with outstanding balance.'], 422);
        }

        try {
            DB::beginTransaction();
            // Delete related records if necessary, or just rely on cascade if configured.
            $user->delete();
            DB::commit();

            return response()->json(['message' => 'Member deleted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Member deletion failed: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete member.'], 500);
        }
    }

    /**
     * Create a loan for a member.
     */
    public function createLoan(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'total_installments' => ['required', 'integer', 'min:1'],
            'interval' => ['required', 'in:daily,weekly,monthly'],
            'description' => ['nullable', 'string'],
            'repayment_start_date' => ['nullable', 'date'],
        ]);

        $loan = DB::transaction(function () use ($user, $data, $request) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

            if ($lockedUser->hasActiveLoan()) {
                throw new \Exception('Member already has an active loan.');
            }

            $perInstallment = round($data['amount'] / $data['total_installments'], 2);

            return QardHasan::create([
                'user_id' => $lockedUser->id,
                'qard_id_string' => 'ADM-' . strtoupper(Str::random(8)),
                'principal_amount' => $data['amount'],
                'total_installments' => $data['total_installments'],
                'per_installment' => $perInstallment,
                'interval' => $data['interval'],
                'status' => 'active',
                'description' => $data['description'] ?? 'Admin created loan',
                'repayment_start_date' => $data['repayment_start_date'] ?? null,
                'disbursed_at' => now(),
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'received_at' => now(),
            ]);
        });

        // Log the action
        Log::info("Admin {$request->user()->id} created loan for member {$user->id}", ['amount' => $data['amount']]);

        return response()->json([
            'message' => 'Loan created successfully.',
            'loan' => $loan,
        ], 201);
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

    /**
     * Get member fines.
     */
    public function fines(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $fines = AttendanceRecord::where('user_id', $user->id)
            ->where(function($q) {
                $q->where('status', 'fine_pending')
                  ->orWhere(function($q2) {
                      $q2->where('lateness_fine_paid', false)
                         ->where('lateness_fine_amount', '>', 0);
                  });
            })
            ->with('meeting')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'membership_number' => $user->membership_number,
                'passport_url' => $user->passport_url,
                'outstanding_fines' => $user->outstanding_fines,
            ],
            'fines' => $fines
        ]);
    }

    /**
     * Waive a specific fine.
     */
    public function waiveFine(Request $request, User $user, AttendanceRecord $attendanceRecord)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        if ($attendanceRecord->user_id !== $user->id) {
            return response()->json(['message' => 'Record mismatch'], 400);
        }

        $this->attendanceService->waiveFine($user, $attendanceRecord);

        return response()->json(['message' => 'Fine waived successfully']);
    }

    /**
     * Waive all fines for a member.
     */
    public function waiveAllFines(Request $request, User $user)
    {
        $this->authorizeAdminAccess($request->user(), $user);

        $this->attendanceService->waiveAllFines($user);

        return response()->json(['message' => 'All fines waived successfully']);
    }
}
