<?php

namespace App\Models\Traits;

use App\Models\TransactionApproval;
use App\Models\LoanPenalty;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait QardHasanMultiSig
{
    public function isHighValue(): bool
    {
        $threshold = config('cooperative.approvals.high_value_loan_threshold', 500000);
        return (float) $this->principal_amount >= (float) $threshold;
    }

    public function hasSufficientApprovals(): bool
    {
        if (!$this->isHighValue()) {
            return true;
        }

        $requiredCount = config('cooperative.approvals.required_approvals_count', 2);
        $approvedCount = $this->transactionApprovals()
            ->where('status', 'approved')
            ->count();

        return $approvedCount >= $requiredCount;
    }

    public function isAwaitingApprovals(): bool
    {
        return $this->isHighValue() && !$this->hasSufficientApprovals();
    }

    public function startPenaltyRecord(): void
    {
        if (!$this->defaulted_at) {
            return;
        }

        LoanPenalty::create([
            'user_id' => $this->user_id,
            'qard_hasan_id' => $this->id,
            'default_started_at' => $this->defaulted_at,
        ]);
    }

    public function completePenaltyRecord(): void
    {
        $penalty = LoanPenalty::where('qard_hasan_id', $this->id)
            ->whereNull('default_cleared_at')
            ->latest()
            ->first();

        if (!$penalty) {
            return;
        }

        $defaultedAt = $this->getOriginal('defaulted_at') ?: $penalty->default_started_at;
        if (!$defaultedAt) {
            return;
        }

        $now = now();
        $monthsDefaulted = (int) $defaultedAt->diffInMonths($now);
        $penaltyUntil = $now->copy()->add($defaultedAt->diff($now));

        $penalty->update([
            'months_defaulted' => $monthsDefaulted,
            'default_cleared_at' => $now,
            'penalty_until' => $penaltyUntil,
        ]);
    }
}
