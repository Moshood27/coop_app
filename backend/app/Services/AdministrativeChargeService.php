<?php

namespace App\Services;

use App\Models\User;
use App\Models\Setting;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdministrativeChargeService
{
    /**
     * Process administrative charges for all eligible users.
     */
    public function processMonthlyCharges(): array
    {
        if (!Setting::get('monthly_fees_enabled', true)) {
            Log::info('Monthly administrative charges are disabled in settings.');
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

        $stats = [
            'total_users' => 0,
            'accrued' => 0,
            'auto_deducted' => 0,
            'failed_auto_deduct' => 0,
            'total_deducted_amount' => 0,
        ];

        // Process users who haven't been charged this month
        $users = User::whereNull('deceased_at')
            ->where(function ($query) use ($period) {
                $query->whereNull('last_admin_charge_at')
                      ->orWhere('last_admin_charge_at', '<', Carbon::now()->startOfMonth());
            })
            ->get();

        foreach ($users as $user) {
            $stats['total_users']++;
            $amount = $user->is_distant ? $meetingFee : $sittingFee;

            DB::transaction(function () use ($user, $amount, $period, &$stats) {
                // 1. Accrue the charge
                $user->admin_charge_balance += $amount;
                $user->last_admin_charge_at = Carbon::now();
                $user->save();
                $stats['accrued']++;

                // 2. Auto-deduct if enabled
                $deducted = false;
                if ($user->admin_charge_auto_deduct && $user->admin_charge_balance > 0) {
                    $deducted = $this->attemptDeduction($user, $stats);
                }

                // 3. Notify about accumulation if not fully settled
                if ($user->admin_charge_balance > 0) {
                    $user->notifyMember(
                        "Administrative Charge Accumulated",
                        "A monthly administrative charge of ₦" . number_format($amount, 2) . " has been applied. Your total pending balance is ₦" . number_format($user->admin_charge_balance, 2) . ". Please fund your wallet for settlement.",
                        [
                            'type' => 'admin_charge_accumulation',
                            'amount' => $amount,
                            'total_pending' => $user->admin_charge_balance,
                            'period' => $period
                        ]
                    );
                }
            });
        }

        return $stats;
    }

    /**
     * Attempt to deduct the accumulated administrative charge from user wallet.
     */
    public function attemptDeduction(User $user, array &$stats = []): bool
    {
        $due = (float) $user->admin_charge_balance;
        if ($due <= 0) return true;

        $balance = (float) $user->balance;
        if ($balance <= 0) {
            if (isset($stats['failed_auto_deduct'])) $stats['failed_auto_deduct']++;
            return false;
        }

        $amountToDeduct = min($due, $balance);

        return DB::transaction(function () use ($user, $amountToDeduct, $due, &$stats) {
            // Deduct from wallet
            $user->decrement('balance', $amountToDeduct);
            $user->decrement('admin_charge_balance', $amountToDeduct);
            $user->refresh();

            // Create transaction record
            $description = $user->is_distant ? 'Monthly Meeting Fee' : 'Monthly Sitting Fee';
            $isAccumulated = $due > ($user->is_distant ? Setting::get('meeting_fee_amount', 1000) : Setting::get('sitting_fee_amount', 300));

            if ($isAccumulated) {
                $description .= ' (Accumulated)';
            }

            $isFullSettlement = $user->admin_charge_balance <= 0;

            WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $amountToDeduct,
                'reference' => 'ADMIN-CHG-' . $user->id . '-' . time(),
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
            if ($user->admin_charge_balance > 0) {
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
}
