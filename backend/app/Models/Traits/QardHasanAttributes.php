<?php

namespace App\Models\Traits;

use Carbon\Carbon;

trait QardHasanAttributes
{
    /**
     * Compute the next due date for this loan based on interval, total installments, and progress.
     * This is a computed helper and does not persist any schedule.
     */
    public function getNextDueAtAttribute(): ?string
    {
        // If not active (not yet disbursed) or already completed, next due is not applicable
        if (! in_array($this->status, ['active', 'defaulted', 'pending'], true)) {
            return null;
        }

        // For pending loans, only show next due if it has been approved or received OR has payments (migrated loans)
        if ($this->status === 'pending' && ! $this->approved_at && ! $this->received_at && (float)$this->paid_amount <= 0) {
            return null;
        }
        if ((float) $this->principal_amount <= 0 || (int) $this->total_installments <= 0) {
            return null;
        }

        $per = (float) $this->per_installment;
        if ($per <= 0) {
            // Derive per installment if not stored or invalid
            $per = round(((float) $this->principal_amount) / max((int) $this->total_installments, 1), 2);
        }

        $paid = (float) $this->paid_amount;
        $installmentsPaid = (int) floor($per > 0 ? ($paid / $per) : 0);
        if ($installmentsPaid >= (int) $this->total_installments) {
            return null; // fully paid
        }

        $schedule = $this->generateInstallmentSchedule();
        if (empty($schedule)) {
            return null;
        }

        // Next installment index is installmentsPaid (0-based)
        $idx = max(0, min($installmentsPaid, count($schedule) - 1));
        $next = $schedule[$idx]['due_at'] ?? null;

        return $next instanceof Carbon ? $next->toISOString() : (is_string($next) ? $next : null);
    }

    public function getExpectedAmountToPayAttribute(): float
    {
        return round(max(0.0, $this->getExpectedAmountTillNextInstallment(now()) - (float)$this->paid_amount), 2);
    }

    /**
     * Calculate the overdue amount for this loan.
     */
    public function getOverdueAmountAttribute(): float
    {
        return $this->getOverdueAmount();
    }

    public function getRemainingPrincipalAttribute(): float
    {
        $remaining = (float) $this->principal_amount - (float) $this->paid_amount;

        return $remaining > 0 ? round($remaining, 2) : 0.0;
    }

    public function getProgressPctAttribute(): float
    {
        if ((float) $this->principal_amount <= 0) {
            return 0.0;
        }
        $pct = ((float) $this->paid_amount / (float) $this->principal_amount) * 100;
        if ($pct > 100) {
            $pct = 100;
        }
        if ($pct < 0) {
            $pct = 0;
        }

        return round($pct, 2);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed' || $this->remaining_principal <= 0.0;
    }

    public function getCreditedAmountAttribute(): float
    {
        return round((float) $this->principal_amount, 2);
    }

    public function getNextInstallmentAmountAttribute(): float
    {
        $per = (float) $this->per_installment;
        if ($per <= 0) {
            $per = round(((float)$this->principal_amount) / max((int)$this->total_installments, 1), 2);
        }

        $paid = (float) $this->paid_amount;
        $installmentsPaid = (int) floor($per > 0 ? ($paid / $per) : 0);

        $paidIntoCurrent = $paid - ($installmentsPaid * $per);
        $remainingOnCurrent = max(0, $per - $paidIntoCurrent);

        if ($remainingOnCurrent > 0.01) {
            return round(min($remainingOnCurrent, $this->remaining_principal), 2);
        }

        return round(min($per, $this->remaining_principal), 2);
    }

    public function getPeriodOfDefaultAttribute(): string
    {
        $startDate = $this->getDefaultStartDate();
        if (!$startDate) return 'None';

        $days = (int) abs(now()->diffInDays($startDate));
        $formattedDuration = \App\Support\DurationHelper::format($days);
        return $startDate->format('d-m-Y') . " ({$formattedDuration})";
    }
}
