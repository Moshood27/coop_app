<?php

namespace App\Models\Traits;

use App\Models\Scheme;
use App\Models\StoreOrder;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;

trait MemberFinancials
{
    /**
     * Compute Savings + Shares totals and 2x eligibility for this user.
     * Returns array: [savings, shares, base, eligibility]
     */
    public function savingsSharesEligibility(): array
    {
        // Scheme IDs for Savings, Shares and Migrated balances that count towards eligibility
        $schemes = Scheme::whereIn('name', [
            'Savings',
            'Shares',
            'Special Savings',
            'Ordinary Savings',
            'Share Capital',
            'Loan Repayment',
            'Building',
            'Development',
            'AGM',
            'Welfare',
            'H Savings'
        ])->pluck('id', 'name');

        $savings = 0.0;
        $shares = 0.0;
        $specialSavings = 0.0;
        $migrated = 0.0;

        if (isset($schemes['Savings'])) {
            $savings += (float) $this->contributions()
                ->where('status', 'success')
                ->where('scheme_id', $schemes['Savings'])
                ->sum('amount');
        }
        if (isset($schemes['Ordinary Savings'])) {
            $savings += (float) $this->contributions()
                ->where('status', 'success')
                ->where('scheme_id', $schemes['Ordinary Savings'])
                ->sum('amount');
        }
        if (isset($schemes['Shares'])) {
            $shares += (float) $this->contributions()
                ->where('status', 'success')
                ->where('scheme_id', $schemes['Shares'])
                ->sum('amount');
        }
        if (isset($schemes['Share Capital'])) {
            $shares += (float) $this->contributions()
                ->where('status', 'success')
                ->where('scheme_id', $schemes['Share Capital'])
                ->sum('amount');
        }
        if (isset($schemes['Special Savings'])) {
            $specialSavings = (float) $this->contributions()
                ->where('status', 'success')
                ->where('scheme_id', $schemes['Special Savings'])
                ->sum('amount');
        }

        // Include other migrated balances in the base for loan eligibility
        foreach (['Loan Repayment', 'Building', 'Development', 'AGM', 'Welfare', 'H Savings'] as $sName) {
            if (isset($schemes[$sName])) {
                $migrated += (float) $this->contributions()
                    ->where('status', 'success')
                    ->where('scheme_id', $schemes[$sName])
                    ->sum('amount');
            }
        }

        $base = round($savings + $shares + $specialSavings + $migrated, 2);
        $eligibility = round($base * 2, 2);

        return [
            'savings' => $savings,
            'shares' => $shares,
            'special_savings' => $specialSavings,
            'migrated_base' => $migrated,
            'base' => $base,
            'eligibility' => $eligibility,
        ];
    }

    /**
     * Compute withdrawable breakdown for the wallet using tiered logic.
     * Debits consume restricted credits first, so available_for_withdrawal reflects
     * what can be cashed out to bank right now.
     */
    public function withdrawableBreakdown(): array
    {
        // Sum credits that are withdrawable (or older rows without the flag)
        $creditsWithdrawable = (float) WalletTransaction::where('user_id', $this->id)
            ->where('type', 'credit')
            ->where(function ($q) {
                $q->where('withdrawable', true)->orWhereNull('withdrawable');
            })
            ->sum('amount');

        // Sum credits explicitly restricted (withdrawable=false)
        $creditsRestricted = (float) WalletTransaction::where('user_id', $this->id)
            ->where('type', 'credit')
            ->where('withdrawable', false)
            ->sum('amount');

        // Sum all debits
        $totalDebits = (float) WalletTransaction::where('user_id', $this->id)
            ->where('type', 'debit')
            ->sum('amount');

        // Identify cash-out debits (bank withdrawals) that must reduce withdrawable immediately
        $cashoutDebits = (float) WalletTransaction::where('user_id', $this->id)
            ->where('type', 'debit')
            ->whereIn('source', ['bank_withdrawal'])
            ->sum('amount');

        $otherDebits = max(0.0, $totalDebits - $cashoutDebits);

        // For non-cashout spending, consume restricted first, then withdrawable
        $debitedFromWithdrawableOther = max(0.0, $otherDebits - $creditsRestricted);

        // Total debited from withdrawable = cash-out debits (always from withdrawable) + spillover from other debits
        $debitedFromWithdrawable = $cashoutDebits + $debitedFromWithdrawableOther;
        $remainingWithdrawable = max(0.0, $creditsWithdrawable - $debitedFromWithdrawable);

        $available = min((float) $this->balance, $remainingWithdrawable);

        return [
            'credits_withdrawable' => round($creditsWithdrawable, 2),
            'credits_restricted' => round($creditsRestricted, 2),
            'total_debits' => round($totalDebits, 2),
            'cashout_debits' => round($cashoutDebits, 2),
            'remaining_withdrawable' => round($remainingWithdrawable, 2),
            'available_for_withdrawal' => round($available, 2),
        ];
    }

    /**
     * Months since the user joined (based on created_at).
     */
    public function monthsInSystem(): int
    {
        // For migrated members, we assume they've passed the probation period (at least 6 months)
        if ($this->migrated_at) {
            return 6;
        }

        if (! $this->created_at) {
            return 0;
        }

        return (int) Carbon::parse($this->created_at)->diffInMonths(now());
    }

    /**
     * Whether the user has any completed loan (status completed or paid >= principal).
     */
    public function hasCompletedLoan(): bool
    {
        return $this->qardHasans()
            ->where(function ($q) {
                $q->where('status', 'completed')
                    ->orWhereColumn('paid_amount', '>=', 'principal_amount');
            })
            ->exists();
    }

    /**
     * Policy-aware eligibility for principal amount.
     * - If first loan (no completed loans): 5% of (Savings + Shares)
     * - Otherwise: 2x (Savings + Shares)
     */
    public function adjustedLoanEligibility(): array
    {
        $calc = $this->savingsSharesEligibility();
        $base = (float) ($calc['base'] ?? 0);
        $months = $this->monthsInSystem();
        $hasCompleted = $this->hasCompletedLoan();

        // Policy: First loan is capped at 5% of savings+shares.
        // Bypassed for migrated members who are assumed to have established history.
        $isFirstLoan = ! $hasCompleted && ! $this->migrated_at;

        $baseAdjusted = $isFirstLoan ? round($base * 0.05, 2) : round($base * 2, 2);
        $scoreEnabled = (bool) \App\Models\Setting::get('loan_credit_score_enabled', config('cooperative.loan_credit_score_enabled', true));

        // Attaqwa Score Bonus: +1% for every 20 points, max +50%
        $scoreBonus = $scoreEnabled ? min(($this->attaqwa_score / 20) / 100, 0.50) : 0.0;
        $finalEligibility = round($baseAdjusted * (1 + $scoreBonus), 2);

        return array_merge($calc, [
            'months_in_system' => $months,
            'is_first_loan' => $isFirstLoan,
            'attaqwa_score' => $this->attaqwa_score,
            'score_bonus_pct' => round($scoreBonus * 100, 2),
            'eligibility_adjusted' => $finalEligibility,
            'score_enabled' => $scoreEnabled,
        ]);
    }

    /**
     * Check if user has an active store financing (Murabaha/Mudarabah) order.
     */
    public function hasActiveStoreFinancing(): bool
    {
        return StoreOrder::where('user_id', $this->id)
            ->whereIn('status', ['murabaha_pending', 'murabaha_active'])
            ->exists();
    }

    public function totalOverdueAmount(): float
    {
        return (float) $this->qardHasans()
            ->whereIn('status', ['active', 'defaulted', 'pending'])
            ->get()
            ->sum(fn($loan) => $loan->getOverdueAmount());
    }

    public function getDefaultDuration(): ?string
    {
        $oldestLoan = $this->qardHasans()
            ->whereIn('status', ['active', 'defaulted', 'pending'])
            ->get()
            ->filter(fn($loan) => $loan->getDefaultStartDate() !== null)
            ->sortBy(fn($loan) => $loan->getDefaultStartDate()->timestamp)
            ->first();

        return $oldestLoan ? $oldestLoan->period_of_default : null;
    }

    public function totalExpectedAmountToPay(): float
    {
        return (float) $this->qardHasans()
            ->whereIn('status', ['active', 'defaulted', 'pending'])
            ->get()
            ->sum('expected_amount_to_pay');
    }

    public function hasActiveLoanPenalty(): bool
    {
        return $this->loan_penalty_until && $this->loan_penalty_until->gt(now());
    }

    /**
     * Sync the user's is_defaulter status based on all their loans.
     */
    public function syncLoanDefaulterStatus(): void
    {
        $hasDefaultedLoan = $this->qardHasans()
            ->whereNotNull('defaulted_at')
            ->where('defaulted_at', '<=', now())
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->whereColumn('paid_amount', '<', 'principal_amount')
            ->exists();

        if ($this->is_defaulter !== $hasDefaultedLoan) {
            $this->is_defaulter = $hasDefaultedLoan;
            $this->save();
        }
    }

    /**
     * Convenience helper: numeric available-for-withdrawal.
     */
    public function availableForWithdrawal(): float
    {
        $b = $this->withdrawableBreakdown();

        return (float) ($b['available_for_withdrawal'] ?? 0.0);
    }

    /**
     * Calculate total assets relevant for Zakat (Naira + Gold + Shares + Savings)
     */
    public function zakatBaseWealth(float $goldPrice): float
    {
        // Savings, Shares are usually stored in Contributions
        $schemes = Scheme::whereIn('name', ['Savings', 'Shares', 'Special Savings', 'Ordinary Savings', 'Share Capital'])->pluck('id');

        $savingsAndShares = (float) $this->contributions()
            ->where('status', 'success')
            ->whereIn('scheme_id', $schemes)
            ->sum('amount');

        $goldValue = round(($this->gold_balance ?? 0) * $goldPrice, 2);
        $walletBalance = (float) ($this->balance ?? 0);

        return (float) round($savingsAndShares + $goldValue + $walletBalance, 2);
    }

    public function syncSchemeBalance(string $schemeName): void
    {
        $columnMap = [
            'Savings' => 'ordinary_savings',
            'Ordinary Savings' => 'ordinary_savings',
            'Sav' => 'ordinary_savings',
            'Shares' => 'shares_capital',
            'Share Capital' => 'shares_capital',
            'Development' => 'development_fund_balance',
            'Building' => 'building_balance',
            'AGM' => 'agm_balance',
            'Loan Repayment' => 'loan_repayment_balance',
            'Fine' => 'fine_balance',
            'Welfare' => 'welfare_balance',
            'Lateness' => 'lateness_balance',
            'Stationery' => 'stationery_balance',
            'Loan Form' => 'loan_form_balance',
            'Others' => 'others_balance',
            'ID Card' => 'id_card_balance',
            'Emergency' => 'emergency_balance',
            'Entrance' => 'entrance_balance',
            'H Savings' => 'h_savings_balance',
            'Investment' => 'investment_balance',
            'Group Savings' => 'group_savings_balance',
            'Special Savings' => 'special_savings_balance',
            'Takaful' => 'takaful_balance',
            'Digital Gold' => 'gold_balance',
            'Dawah Fund' => 'dawah_fund_balance',
            'SITTING' => 'sitting_balance',
        ];

        if (isset($columnMap[$schemeName])) {
            $column = $columnMap[$schemeName];

            // Safely sum all schemes that map to the same column
            $relatedSchemes = array_keys(array_filter($columnMap, fn($c) => $c === $column));

            $actualTotal = (float) $this->contributions()
                ->whereHas('scheme', fn($q) => $q->whereIn('name', $relatedSchemes))
                ->where('status', 'success')
                ->sum('amount');

            $this->forceFill([$column => $actualTotal])->save();
        }
    }

    public function getTotalBalance(): float
    {
        $balanceColumns = [
            'balance',
            'ordinary_savings',
            'special_savings_balance',
            'shares_capital',
            'building_balance',
            'development_fund_balance',
            'agm_balance',
            'loan_repayment_balance',
            'fine_balance',
            'welfare_balance',
            'lateness_balance',
            'stationery_balance',
            'loan_form_balance',
            'others_balance',
            'id_card_balance',
            'emergency_balance',
            'entrance_balance',
            'h_savings_balance',
            'investment_balance',
            'group_savings_balance',
            'takaful_balance',
            'dawah_fund_balance',
            'sitting_balance',
        ];

        $total = 0.0;
        foreach ($balanceColumns as $column) {
            $total += (float) ($this->{$column} ?? 0.0);
        }

        return round($total, 2);
    }

    /**
     * Check if the user is currently in the nursing mother grace period.
     * (Grace period of X months after baby birth or explicit grace until date).
     */
    public function isInNursingMotherGracePeriod(): bool
    {
        // Only active if approved by admin
        if ($this->nursing_mother_status !== 'approved') {
            return false;
        }

        if ($this->nursing_mother_grace_until && now()->isBefore($this->nursing_mother_grace_until)) {
            return true;
        }

        if ($this->is_nursing_mother) {
            return true;
        }

        if ($this->baby_birth_date) {
            $months = (int) \App\Models\Setting::get('nursing_mother_grace_period_months', 3);
            $graceAfterBirth = $this->baby_birth_date->copy()->addMonths($months);
            return now()->isBefore($graceAfterBirth);
        }

        return false;
    }
}
