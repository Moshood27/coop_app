<?php

namespace App\Models\Traits;

use Carbon\Carbon;

trait QardHasanCalculations
{
    /**
     * Generate a simple installment schedule as an array of [index, due_at (Carbon), amount].
     * Start date: approved_at when present, otherwise created_at; first installment is one interval after start.
     */
    public function generateInstallmentSchedule(?Carbon $startAt = null): array
    {
        $cacheKey = $startAt ? $startAt->timestamp : 'default';
        if (isset($this->installmentSchedule[$cacheKey])) {
            return $this->installmentSchedule[$cacheKey];
        }

        $total = (int) $this->total_installments;
        if ($total <= 0) {
            $schedule = $this->installmentSchedule ?? [];
            $schedule[$cacheKey] = [];
            $this->installmentSchedule = $schedule;
            return [];
        }

        $per = (float) $this->per_installment;
        if ($per <= 0) {
            $per = round(((float) $this->principal_amount) / max($total, 1), 2);
        }

        $interval = strtolower((string) $this->interval ?: 'monthly');
        $useExplicitStart = false;
        if ($startAt) {
            $start = $startAt->copy();
        } elseif ($this->repayment_start_date) {
            $start = $this->repayment_start_date->copy();
            $useExplicitStart = true;
        } else {
            $start = ($this->received_at ?: ($this->approved_at ?: ($this->created_at ?: now())));
            $start = ($start instanceof Carbon) ? $start->copy() : Carbon::parse((string) $start);
        }

        $items = [];
        $cursor = $start->copy();
        for ($i = 0; $i < $total; $i++) {
            if ($i > 0 || !$useExplicitStart) {
                $cursor = $this->addInterval($cursor, $interval); // move by one interval each time
            }
            $items[] = [
                'index' => $i + 1,
                'due_at' => $cursor->copy(),
                'due_date' => $cursor->toDateString(),
                'amount' => $per,
            ];
        }

        // Force Ascending Order (Fixes "descending order" issue for migrated loans)
        usort($items, fn($a, $b) => $a['due_at']->timestamp <=> $b['due_at']->timestamp);

        // Re-index after sort
        foreach ($items as $idx => &$item) {
            $item['index'] = $idx + 1;
        }

        $schedule = $this->installmentSchedule ?? [];
        $schedule[$cacheKey] = $items;
        $this->installmentSchedule = $schedule;
        return $items;
    }

    /**
     * Add interval (daily|weekly|monthly) to a Carbon date and return a cloned instance.
     */
    public function addInterval(Carbon $date, string $interval): Carbon
    {
        $d = $date->copy();
        $key = strtolower(trim($interval));

        return match ($key) {
            'daily' => $d->addDay(),
            'weekly' => $d->addWeek(),
            'quarterly' => $d->addQuarter(),
            'yearly' => $d->addYear(),
            default => $d->addMonth(), // monthly fallback
        };
    }

    /**
     * Subtract interval (daily|weekly|monthly) from a Carbon date and return a cloned instance.
     */
    public function subInterval(Carbon $date, string $interval): Carbon
    {
        $d = $date->copy();
        $key = strtolower(trim($interval));

        return match ($key) {
            'daily' => $d->subDay(),
            'weekly' => $d->subWeek(),
            'quarterly' => $d->subQuarter(),
            'yearly' => $d->subYear(),
            default => $d->subMonth(), // monthly fallback
        };
    }

    public function allGuarantorsAccepted(): bool
    {
        $g = $this->guarantors;
        if (! $g || $g->isEmpty()) {
            return false;
        }

        // Require at least 2 guarantors and all must be accepted
        return $g->count() >= 2 && $g->every(fn ($u) => ($u->pivot?->status) === 'accepted');
    }

    public function pendingGuarantorCount(): int
    {
        return (int) ($this->guarantors?->filter(fn ($u) => ($u->pivot?->status) === 'pending')->count() ?? 0);
    }

    /**
     * Sync the user's is_defaulter status based on all their loans.
     */
    public function syncUserDefaulterStatus(): void
    {
        if ($this->user) {
            $this->user->syncLoanDefaulterStatus();
        }
    }

    /**
     * Calculate the expected amount including the next installment after a certain date.
     */
    public function getExpectedAmountTillNextInstallment(?Carbon $asAt = null): float
    {
        $asAt = $asAt ?: now();
        $schedule = $this->generateInstallmentSchedule();

        // Find the first installment that is due exactly on or after $asAt
        $targetDate = $asAt;
        foreach ($schedule as $item) {
            if ($item['due_at']->greaterThanOrEqualTo($asAt)) {
                $targetDate = $item['due_at'];
                break;
            }
        }

        return $this->getExpectedAmountToDate($targetDate);
    }

    /**
     * Calculate the expected amount to be paid by a certain date.
     */
    public function getExpectedAmountToDate(?Carbon $asAt = null): float
    {
        $asAt = $asAt ?: now();
        if ($this->status === 'pending' && ! $this->approved_at && ! $this->received_at && (float)$this->paid_amount <= 0) return 0.0;

        $per = (float) $this->per_installment;
        if ($per <= 0) {
            $per = round(((float)$this->principal_amount) / max((int)$this->total_installments, 1), 2);
        }
        if ($per <= 0) return 0.0;

        $schedule = $this->generateInstallmentSchedule();
        if (empty($schedule)) return 0.0;

        $dueCount = 0;
        foreach ($schedule as $item) {
            $dueAt = $item['due_at'] instanceof Carbon ? $item['due_at'] : Carbon::parse((string) $item['due_at']);
            if ($dueAt->lessThanOrEqualTo($asAt)) {
                $dueCount++;
            } else {
                break;
            }
        }

        return round(min($dueCount * $per, (float) $this->principal_amount), 2);
    }

    /**
     * Calculate the overdue amount for this loan.
     */
    public function getOverdueAmount(?Carbon $asAt = null): float
    {
        $asAt = $asAt ?: now();

        if (! in_array($this->status, ['active', 'defaulted', 'pending'])) return 0.0;

        // If the loan is marked as defaulted, the full remaining balance is considered overdue (acceleration)
        if ($this->defaulted_at && $this->defaulted_at->year > 1970) {
            if ($this->defaulted_at->lessThanOrEqualTo($asAt)) {
                return (float) $this->remaining_principal;
            }

            // If the defaulted date is in the future, the amount defaulted should be 0.00
            return 0.0;
        }

        $expectedPaid = $this->getExpectedAmountToDate($asAt);
        $alreadyPaid = (float) $this->paid_amount;
        $overdue = round(max(0.0, $expectedPaid - $alreadyPaid), 2);

        $remaining = max(0.0, (float) $this->principal_amount - $alreadyPaid);
        if ($overdue > $remaining) $overdue = $remaining;

        return $overdue;
    }

    /**
     * Get the number of days the loan is overdue.
     */
    public function getOverdueDays(?Carbon $asAt = null): int
    {
        $asAt = $asAt ?: now();

        // If explicitly marked as defaulted, calculate from defaulted_at
        if ($this->defaulted_at && $this->defaulted_at->year > 1970) {
            if ($this->defaulted_at->lessThanOrEqualTo($asAt)) {
                return (int) abs($asAt->diffInDays($this->defaulted_at));
            }
        }

        if ($this->getOverdueAmount($asAt) <= 0) return 0;

        $schedule = $this->generateInstallmentSchedule();
        $per = (float) $this->per_installment;
        if ($per <= 0) {
            $per = round(((float)$this->principal_amount) / max((int)$this->total_installments, 1), 2);
        }

        $paid = (float) $this->paid_amount;
        $installmentsPaid = (int) floor($per > 0 ? ($paid / $per) : 0);

        // The first installment that is NOT paid but its due_at is in the past
        if (isset($schedule[$installmentsPaid])) {
            $dueAt = $schedule[$installmentsPaid]['due_at'];
            if ($dueAt->lessThan($asAt)) {
                return (int) abs($asAt->diffInDays($dueAt));
            }
        }

        return 0;
    }

    public function getDefaultStartDate(?Carbon $asAt = null): ?Carbon
    {
        $asAt = $asAt ?: now();
        if ($this->defaulted_at && $this->defaulted_at->year > 1970) {
            if ($this->defaulted_at->lessThanOrEqualTo($asAt)) {
                return $this->defaulted_at;
            }
        }

        if ($this->getOverdueAmount($asAt) <= 0) return null;

        $schedule = $this->generateInstallmentSchedule();
        $per = (float) $this->per_installment;
        if ($per <= 0) {
            $per = round(((float)$this->principal_amount) / max((int)$this->total_installments, 1), 2);
        }

        $paid = (float) $this->paid_amount;
        $installmentsPaid = (int) floor($per > 0 ? ($paid / $per) : 0);

        if (isset($schedule[$installmentsPaid])) {
            $dueAt = $schedule[$installmentsPaid]['due_at'];
            if ($dueAt->lessThan($asAt)) {
                return $dueAt;
            }
        }

        return null;
    }
}
