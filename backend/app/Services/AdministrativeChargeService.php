<?php

namespace App\Services;

use App\Models\User;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Models\Contribution;
use App\Models\Scheme;
use App\Models\CharityEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdministrativeChargeService
{
    /**
     * Process administrative charges for all eligible users.
     * This accrues the monthly fee and attempts deduction.
     */
    public function processMonthlyCharges(): array
    {
        $sittingEnabled = Setting::get('sitting_fees_enabled', Setting::get('monthly_fees_enabled', true));
        $meetingEnabled = Setting::get('meeting_fees_enabled', Setting::get('monthly_fees_enabled', true));

        if (!$sittingEnabled && !$meetingEnabled) {
            Log::info('Monthly administrative charges (Sitting and Meeting fees) are disabled in settings.');
            return [
                'total_users' => 0,
                'accrued' => 0,
                'auto_deducted' => 0,
                'failed_auto_deduct' => 0,
                'total_deducted_amount' => 0,
                'status' => 'disabled'
            ];
        }

        $sittingFee = Setting::get('sitting_fee_amount', config('cooperative.admin_charges.amount', 300));
        $meetingFee = Setting::get('meeting_fee_amount', 1000);
        $period = Carbon::now()->format('Y-m');
        $sittingScheme = Scheme::where('name', 'SITTING')->first();

        $stats = [
            'total_users' => 0,
            'accrued' => 0,
            'auto_deducted' => 0,
            'failed_auto_deduct' => 0,
            'total_deducted_amount' => 0,
        ];

        $processBatch = function ($users) use ($sittingFee, $meetingFee, $period, $sittingScheme, $sittingEnabled, $meetingEnabled, &$stats) {
            foreach ($users as $user) {
                try {
                    // Check if enabled for this specific user type
                    $isEnabled = $user->is_distant ? $meetingEnabled : $sittingEnabled;
                    if (!$isEnabled) continue;

                    $stats['total_users']++;

                    $amount = $user->is_distant ? $meetingFee : $sittingFee;

                    DB::transaction(function () use ($user, $amount, $period, $sittingScheme, &$stats) {
                        // Lock the user record to prevent concurrent processing
                        $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();

                        if (!$lockedUser) return;

                        // Double check if already charged this month (in case another process just finished)
                        if ($lockedUser->last_admin_charge_at && $lockedUser->last_admin_charge_at >= Carbon::now()->startOfMonth()) {
                            return;
                        }

                        // Safety Check: Avoid double charging if they already paid this month (via old system or manual entry)
                        if ($sittingScheme) {
                            $alreadyPaid = Contribution::where('user_id', $lockedUser->id)
                                ->where('scheme_id', $sittingScheme->id)
                                ->where('status', 'success')
                                ->where('paid_at', '>=', Carbon::now()->startOfMonth())
                                ->exists();

                            if ($alreadyPaid) {
                                $lockedUser->update(['last_admin_charge_at' => Carbon::now()]);
                                return;
                            }
                        }

                        // 1. Accrue the charge
                        $lockedUser->admin_charge_balance += $amount;
                        $lockedUser->last_admin_charge_at = Carbon::now();
                        $lockedUser->save();
                        $stats['accrued']++;

                        // 2. Auto-deduct (Mandatory if funds available and enabled)
                        if ($lockedUser->admin_charge_balance > 0 && $this->isAutoDeductionEnabled($lockedUser)) {
                            $this->attemptDeduction($lockedUser, $stats);
                        }

                        // 3. Notify about accumulation if not fully settled
                        $lockedUser->refresh();
                        if ($lockedUser->admin_charge_balance > 0) {
                            $lockedUser->notifyMember(
                                "Administrative Charge Accumulated",
                                "A monthly administrative charge of ₦" . number_format($amount, 2) . " has been applied. Your total pending balance is ₦" . number_format($lockedUser->admin_charge_balance, 2) . ". Please fund your wallet for settlement.",
                                [
                                    'type' => 'admin_charge_accumulation',
                                    'amount' => $amount,
                                    'total_pending' => $lockedUser->admin_charge_balance,
                                    'period' => $period
                                ]
                            );
                        }
                    });
                } catch (\Throwable $e) {
                    Log::error("Failed to process monthly charge for user {$user->id}: " . $e->getMessage());
                }
            }
        };

        // Process users who haven't been charged this month
        $query = User::whereNull('deceased_at')
            ->where(function ($query) use ($period) {
                $query->whereNull('last_admin_charge_at')
                      ->orWhere('last_admin_charge_at', '<', Carbon::now()->startOfMonth());
            });

        if (class_exists('Laravel\Telescope\Telescope')) {
            \Laravel\Telescope\Telescope::withoutRecording(function () use ($query, $processBatch) {
                $query->chunkById(100, $processBatch);
            });
        } else {
            $query->chunkById(100, $processBatch);
        }

        return $stats;
    }

    /**
     * Settle all outstanding administrative charges for users with balance > 0 and wallet funds.
     */
    public function settleAllOutstandingCharges(): array
    {
        $stats = [
            'total_users_checked' => 0,
            'settled_users' => 0,
            'total_deducted_amount' => 0,
        ];

        $settleBatch = function ($users) use (&$stats) {
            foreach ($users as $user) {
                try {
                    $stats['total_users_checked']++;
                    $beforeBalance = (float) $user->balance;

                    if ($this->isAutoDeductionEnabled($user) && $this->attemptDeduction($user)) {
                        $user->refresh();
                        $deducted = $beforeBalance - (float) $user->balance;
                        if ($deducted > 0) {
                            $stats['settled_users']++;
                            $stats['total_deducted_amount'] += $deducted;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("Failed to settle outstanding charges for user {$user->id}: " . $e->getMessage());
                }
            }
        };

        $query = User::whereNull('deceased_at')
            ->where('admin_charge_balance', '>', 0)
            ->where('balance', '>', 0);

        if (class_exists('Laravel\Telescope\Telescope')) {
            \Laravel\Telescope\Telescope::withoutRecording(function () use ($query, $settleBatch) {
                $query->chunkById(100, $settleBatch);
            });
        } else {
            $query->chunkById(100, $settleBatch);
        }

        return $stats;
    }

    /**
     * Attempt to deduct the accumulated administrative charge from user wallet.
     */
    public function attemptDeduction(User $user, array &$stats = []): bool
    {
        if ($user->skip_auto_collection || User::$global_skip_auto_collection || !$this->isAutoDeductionEnabled($user)) {
            return false;
        }

        return DB::transaction(function () use ($user, &$stats) {
            // Lock the user record to prevent concurrent deductions
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            if (!$user) return false;

            $due = (float) $user->admin_charge_balance;
            if ($due <= 0) return true;

            $balance = (float) $user->balance;
            if ($balance <= 0) {
                if (isset($stats['failed_auto_deduct'])) $stats['failed_auto_deduct']++;
                return false;
            }

            $amountToDeduct = min($due, $balance);

            if ($amountToDeduct <= 0) {
                return false;
            }

            // Deduct from wallet
            $user->decrement('balance', $amountToDeduct);
            $user->refresh();

            // Find SITTING scheme
            $scheme = Scheme::where('name', 'SITTING')->first();

            // Create Contribution (this will trigger UserObserver/Contribution observer to decrement admin_charge_balance)
            $description = $user->is_distant ? 'Meeting Fee (Distant)' : 'Sitting Fee (Regular)';
            $isAccumulated = $due > ($user->is_distant ? Setting::get('meeting_fee_amount', 1000) : Setting::get('sitting_fee_amount', 300));

            if ($isAccumulated) {
                $description .= ' (Accumulated)';
            }

            $reference = 'ADMIN-CHG-' . $user->id . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));

            Contribution::create([
                'user_id' => $user->id,
                'scheme_id' => $scheme?->id,
                'amount' => $amountToDeduct,
                'reference' => $reference,
                'status' => 'success',
                'payment_method' => 'wallet',
                'notes' => $description,
                'paid_at' => now(),
            ]);

            $user->refresh();
            $isFullSettlement = $user->admin_charge_balance <= 0;

            WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $amountToDeduct,
                'reference' => $reference,
                'source' => 'admin_charge',
                'meta' => [
                    'description' => $description,
                    'period' => Carbon::now()->format('Y-m'),
                    'full_settlement' => $isFullSettlement,
                    'remaining_due' => $user->admin_charge_balance
                ]
            ]);

            if (isset($stats['auto_deducted'])) $stats['auto_deducted']++;
            if (isset($stats['total_deducted_amount'])) $stats['total_deducted_amount'] += $amountToDeduct;

            // Notification
            $title = $isFullSettlement ? "Admin Charge Settled" : "Admin Charge Partial Payment";
            $message = "₦" . number_format($amountToDeduct, 2) . " has been deducted from your wallet for administrative charges.";

            if (!$isFullSettlement) {
                $message .= " Remaining balance: ₦" . number_format($user->admin_charge_balance, 2);
            }

            $user->notifyMember($title, $message, [
                'type' => 'admin_charge_deduction',
                'amount' => $amountToDeduct,
                'remaining' => $user->admin_charge_balance,
                'full_settlement' => $isFullSettlement
            ]);

            return true;
        });
    }

    /**
     * Settle administrative charges manually from user wallet.
     */
    public function settleAdminChargeManually(User $user, ?float $amount = null): array
    {
        return DB::transaction(function () use ($user, $amount) {
            // Lock the user record
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            if (!$user) {
                throw new \Exception("Member not found.");
            }

            $due = (float) $user->admin_charge_balance;

            if ($due <= 0) {
                throw new \Exception("Member has no outstanding administrative charges.");
            }

            $amountToPay = $amount ?? $due;
            $amountToPay = min($amountToPay, $due);

            if ($amountToPay <= 0) {
                throw new \Exception("Invalid payment amount.");
            }

            if ((float) $user->balance < $amountToPay) {
                throw new \Exception("Insufficient wallet balance. Available: ₦" . number_format($user->balance, 2));
            }

            // Deduct from wallet
            $user->decrement('balance', $amountToPay);
            $user->refresh();

            // Find SITTING scheme
            $scheme = Scheme::where('name', 'SITTING')->first();

            // Create Contribution
            $description = ($user->is_distant ? 'Meeting Fee (Distant)' : 'Sitting Fee (Regular)') . ' - Manual Settlement';
            $reference = 'ADMIN-SETTLE-' . $user->id . '-' . time();

            Contribution::create([
                'user_id' => $user->id,
                'scheme_id' => $scheme?->id,
                'amount' => $amountToPay,
                'reference' => $reference,
                'status' => 'success',
                'payment_method' => 'wallet',
                'notes' => $description,
                'paid_at' => now(),
            ]);

            $user->refresh();
            $isFullSettlement = $user->admin_charge_balance <= 0;

            $transaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $amountToPay,
                'reference' => $reference,
                'source' => 'admin_charge',
                'meta' => [
                    'description' => $description,
                    'full_settlement' => $isFullSettlement,
                    'remaining_due' => $user->admin_charge_balance,
                    'manual' => true
                ]
            ]);

            // Notification
            $title = $isFullSettlement ? "Admin Charge Settled" : "Admin Charge Partial Payment";
            $message = "₦" . number_format($amountToPay, 2) . " has been manually deducted from your wallet for administrative charges.";

            if (!$isFullSettlement) {
                $message .= " Remaining balance: ₦" . number_format($user->admin_charge_balance, 2);
            }

            $user->notifyMember($title, $message, [
                'type' => 'admin_charge_manual_settlement',
                'amount' => $amountToPay,
                'remaining' => $user->admin_charge_balance,
                'full_settlement' => $isFullSettlement
            ]);

            return [
                'success' => true,
                'amount_paid' => $amountToPay,
                'remaining_due' => (float) $user->admin_charge_balance,
                'transaction' => $transaction
            ];
        });
    }

    /**
     * Calculate and apply all applicable deductions to an incoming amount.
     * Returns the net amount and a summary of deductions.
     * This method DEBITS the user's wallet balance for the deductions.
     * The caller is responsible for CREDITING the user's wallet with the GROSS amount first.
     */
    public function applyDeductionsFromWallet(User $user, float $grossAmount, bool $applyMaintenanceCharge = true, string $referenceBase = 'INFLOW', array $excludeSchemes = []): array
    {
        return DB::transaction(function () use ($user, $grossAmount, $applyMaintenanceCharge, $referenceBase, $excludeSchemes) {
            $deductions = [];
            $currentAmount = $grossAmount;

            $excludeSchemesUpper = array_map('strtoupper', $excludeSchemes);

            // 1. Maintenance Charge (if applicable)
            if ($applyMaintenanceCharge) {
                try {
                    $mCharge = $this->calculateMaintenanceCharge($grossAmount);
                    if ($mCharge > 0) {
                        $deductionAmount = min($mCharge, $currentAmount);
                        if ($deductionAmount > 0) {
                            $user->decrement('balance', $deductionAmount);
                            WalletTransaction::create([
                                'user_id' => $user->id,
                                'type' => 'debit',
                                'amount' => $deductionAmount,
                                'reference' => $referenceBase . '_MTC',
                                'source' => 'maintenance_charge',
                                'meta' => ['description' => 'System maintenance charge', 'gross_amount' => $grossAmount]
                            ]);
                            $currentAmount -= $deductionAmount;
                            $deductions['maintenance_charge'] = $deductionAmount;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("Failed to apply maintenance charge: " . $e->getMessage());
                }
            }

            // 2. Fines Recovery
            try {
                $finesDue = (float) $user->outstanding_fines;
                $autoFineEnabled = (bool) Setting::get('auto_fine_deduction_enabled', true);
                $typeAutoDeduct = $this->isAutoDeductionEnabled($user);

                if ($autoFineEnabled && $typeAutoDeduct && $finesDue > 0 && $currentAmount > 0 && !in_array('FINE', $excludeSchemesUpper)) {
                    $fineDeduction = min($finesDue, $currentAmount);

                    $user->decrement('outstanding_fines', $fineDeduction);
                    $user->decrement('balance', $fineDeduction);

                    WalletTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'debit',
                        'amount' => $fineDeduction,
                        'reference' => $referenceBase . '_FINE',
                        'source' => 'attendance_fine_collection',
                        'meta' => ['description' => 'Fine recovery from inflow', 'gross_inflow' => $grossAmount]
                    ]);

                    CharityEntry::create([
                        'user_id' => $user->id,
                        'source' => 'Attendance Fine Collection',
                        'amount' => $fineDeduction,
                        'note' => 'Recovery from inflow',
                        'status' => 'processed',
                        'processed_at' => now(),
                    ]);

                    app(\App\Services\AttendanceService::class)->settleOutstandingFines($user, $fineDeduction);

                    $currentAmount -= $fineDeduction;
                    $deductions['fines'] = $fineDeduction;
                }
            } catch (\Throwable $e) {
                Log::error("Failed to recover fines during inflow: " . $e->getMessage());
            }

            // 3. Administrative Charges (Sitting Fees)
            try {
                $adminDue = (float) $user->admin_charge_balance;
                if ($this->isAutoDeductionEnabled($user) && $adminDue > 0 && $currentAmount > 0 && !in_array('SITTING', $excludeSchemesUpper)) {
                    $adminDeduction = min($adminDue, $currentAmount);

                    $scheme = Scheme::where('name', 'SITTING')->first();
                    if ($scheme) {
                        $description = ($user->is_distant ? 'Meeting Fee (Distant)' : 'Sitting Fee (Regular)') . ' (Inflow Recovery)';
                        $ref = $referenceBase . '_ADM';

                        Contribution::create([
                            'user_id' => $user->id,
                            'scheme_id' => $scheme->id,
                            'amount' => $adminDeduction,
                            'reference' => $ref,
                            'status' => 'success',
                            'payment_method' => 'inflow_deduction',
                            'notes' => $description,
                            'paid_at' => now(),
                        ]);

                        // NOTE: admin_charge_balance is decremented by ContributionObserver/booted method
                        // We ONLY decrement the wallet balance here
                        $user->decrement('balance', $adminDeduction);

                        // We also record a separate wallet transaction for clarity if needed,
                        // but Contribution::created might already record one?
                        // Actually, regular contributions don't record wallet transactions automatically.
                        WalletTransaction::create([
                            'user_id' => $user->id,
                            'type' => 'debit',
                            'amount' => $adminDeduction,
                            'reference' => $ref,
                            'source' => 'admin_charge',
                            'meta' => [
                                'description' => $description,
                                'gross_inflow' => $grossAmount,
                                'remaining_due' => (float)$user->admin_charge_balance // This will be updated after commit
                            ]
                        ]);

                        $currentAmount -= $adminDeduction;
                        $deductions['admin_charges'] = $adminDeduction;
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Failed to recover admin charges during inflow: " . $e->getMessage());
            }

            return [
                'gross_amount' => $grossAmount,
                'net_amount' => round(max(0, $currentAmount), 2),
                'deductions' => $deductions
            ];
        });
    }

    /**
     * Calculate system maintenance charge for wallet top-ups.
     */
    public function calculateMaintenanceCharge(float $amount): float
    {
        $percentage = Setting::get('wallet_maintenance_charge_percentage', config('cooperative.wallet.maintenance_charge.percentage', 1)) / 100;
        $maxCharge = Setting::get('wallet_maintenance_charge_max', config('cooperative.wallet.maintenance_charge.max_amount', 500));

        return round(min($amount * $percentage, (float) $maxCharge), 2);
    }

    /**
     * Apply a manual credit or debit transaction with all applicable charges.
     */
    public function applyManualTransaction(User $user, float $amount, string $type, ?string $note = null): array
    {
        return DB::transaction(function () use ($user, $amount, $type, $note) {
            $maintenanceCharge = $this->calculateMaintenanceCharge($amount);

            if ($type === 'credit') {
                $actualAmount = $amount - $maintenanceCharge;
                $user->increment('balance', $actualAmount);
            } else {
                $actualAmount = $amount + $maintenanceCharge;
                if ((float) $user->balance < $actualAmount) {
                    throw new \Exception("Insufficient balance to cover the debit amount plus maintenance charge of ₦" . number_format($maintenanceCharge, 2));
                }
                $user->decrement('balance', $actualAmount);
            }

            $user->refresh();

            // 1. Create main transaction record
            $transaction = WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $actualAmount,
                'reference' => 'MANUAL-' . strtoupper($type) . '-' . $user->id . '-' . time(),
                'source' => 'manual',
                'meta' => [
                    'note' => $note,
                    'maintenance_charge' => $maintenanceCharge,
                    'gross_amount' => $amount,
                    'admin_id' => auth()->id(),
                ]
            ]);

            // 2. Process pending administrative charges
            $adminChargeDeducted = 0;
            if ($this->isAutoDeductionEnabled($user) && $user->admin_charge_balance > 0) {
                $beforeAdminCharge = (float) $user->balance;
                if ($this->attemptDeduction($user)) {
                    $user->refresh();
                    $adminChargeDeducted = $beforeAdminCharge - (float) $user->balance;
                }
            }

            return [
                'transaction' => $transaction,
                'gross_amount' => $amount,
                'maintenance_charge' => $maintenanceCharge,
                'actual_amount' => $actualAmount,
                'admin_charge_deducted' => $adminChargeDeducted,
                'new_balance' => (float) $user->balance,
            ];
        });
    }
    /**
     * Check if auto-deduction is enabled for a specific user type.
     */
    public function isAutoDeductionEnabled(User $user): bool
    {
        $settingKey = $user->is_distant ? 'auto_meeting_fine_deduction_enabled' : 'auto_sitting_fine_deduction_enabled';

        // Fallback to old setting for backward compatibility during transition if needed
        return (bool) Setting::get($settingKey, Setting::get('auto_admin_charge_deduction_enabled', true));
    }

    /**
     * Refund a specific charge or fine transaction.
     */
    public function refundTransaction(WalletTransaction $record): bool
    {
        // Check if already refunded
        $alreadyRefunded = WalletTransaction::where('reference', 'REFUND-' . $record->reference)->exists();
        if ($alreadyRefunded) {
            throw new \Exception("This transaction has already been refunded.");
        }

        User::$global_skip_auto_collection = true;

        try {
            return DB::transaction(function () use ($record) {
                // Re-fetch user with lock to ensure fresh data and prevent race conditions
                $user = User::where('id', $record->user_id)->lockForUpdate()->first();
                if (!$user) throw new \Exception("User not found.");

                // Set flag to skip auto-deductions during the refund process
                $user->skip_auto_collection = true;

                $amount = (float)$record->amount;

                // 1. Handle Admin Charge
                if ($record->source === 'admin_charge') {
                    $contribution = Contribution::where('user_id', $user->id)
                        ->where('reference', $record->reference)
                        ->first();

                    if ($contribution) {
                        $contribution->delete();
                    }

                    $user->increment('balance', $amount);
                    // We do NOT increment admin_charge_balance here because a refund
                    // usually means the charge was a discrepancy or double charge.
                    // Incrementing it would make it unavailable in the member's "Available Balance".
                }
                // 2. Handle Fine Collection
                elseif (in_array($record->source, ['attendance_fine_collection', 'attendance_fine'])) {
                    // Delete charity entry if exists
                    CharityEntry::where('user_id', $user->id)
                        ->where('amount', $amount)
                        ->whereBetween('created_at', [$record->created_at->subSeconds(10), $record->created_at->addSeconds(10)])
                        ->delete();

                    // If it was a fine collection, delete the associated Contribution if exists
                    if ($record->source === 'attendance_fine_collection') {
                        Contribution::where('user_id', $user->id)
                            ->where('reference', $record->reference)
                            ->delete();
                    }

                    // We do NOT revert attendance records or increment outstanding_fines here
                    // to ensure the refund reflects in the member's "Available Balance".
                    // If an admin wants to re-fine, they can do so manually.

                    $user->increment('balance', $amount);
                }
                // 3. Handle Maintenance Charge or other charges
                else {
                    $user->increment('balance', $amount);
                }

                // Create Refund Transaction
                WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'reference' => 'REFUND-' . $record->reference,
                    'source' => 'refund',
                    'withdrawable' => true,
                    'meta' => [
                        'original_tx_id' => $record->id,
                        'original_source' => $record->source,
                        'description' => "Refund for " . ucwords(str_replace('_', ' ', (string)$record->source)),
                        'admin_id' => auth()->id(),
                    ]
                ]);

                // Notify Member
                $title = "Refund Processed";
                $sourceName = ucwords(str_replace('_', ' ', (string)$record->source));
                $message = "A refund of ₦" . number_format($amount, 2) . " has been credited to your wallet for: " . $sourceName;

                // Refresh user to get final balance for notification
                $user->refresh();

                $user->notifyMember($title, $message, [
                    'type' => 'refund',
                    'amount' => $amount,
                    'balance' => $user->balance,
                    'source' => $record->source,
                    'reference' => 'REFUND-' . $record->reference,
                ], ['mail', 'push', 'database']);

                return true;
            });
        } finally {
            User::$global_skip_auto_collection = false;
        }
    }
}
