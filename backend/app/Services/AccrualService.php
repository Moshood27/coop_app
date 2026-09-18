<?php

namespace App\Services;

use App\Models\AccrualSchedule;
use App\Models\RecurringJournal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AccrualService
{
    public function featureEnabled(): bool
    {
        return config('accounting.features.accruals_deferrals') && (
            Schema::hasTable('accrual_schedules') || Schema::hasTable('recurring_journals')
        );
    }

    public function postMonthlyAccruals(Carbon $period, bool $dryRun = false): array
    {
        if (!Schema::hasTable('accrual_schedules')) {
            return [0, 0.0];
        }
        $count = 0; $total = 0.0;
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();

        $schedules = AccrualSchedule::query()
            ->where('active', true)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->where(function ($q) use ($start) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start->toDateString());
            })
            ->get();

        /** @var \App\Services\LedgerService $ledger */
        $ledger = app(\App\Services\LedgerService::class);
        foreach ($schedules as $s) {
            $months = $this->estimateMonths($s);
            if ($months <= 0) { continue; }
            $perMonth = round(((float)$s->amount_total) / $months, 2);
            if ($perMonth <= 0) { continue; }

            $count++; $total += $perMonth;
            if ($dryRun) { continue; }

            try {
                if (method_exists($ledger, 'recordSimple')) {
                    $ledger->recordSimple(
                        [
                            'date' => $end->toDateString(),
                            'description' => 'Accrual: '.$s->name.' - '.$period->format('M Y'),
                            'reference' => 'ACCR-'.$period->format('Ym').'-'.$s->id,
                        ],
                        (int)$s->debit_account_id,
                        (int)$s->credit_account_id,
                        (float)$perMonth
                    );
                }
                if (Schema::hasColumn('accrual_schedules', 'posted_until')) {
                    $s->posted_until = $end->toDateString();
                    $s->save();
                }
            } catch (\Throwable $e) {
                Log::error('Failed posting accrual', ['schedule_id' => $s->id, 'message' => $e->getMessage()]);
            }
        }

        return [$count, round($total, 2)];
    }

    protected function estimateMonths($s): int
    {
        $start = $s->start_date ? Carbon::parse($s->start_date)->startOfMonth() : Carbon::now()->startOfMonth();
        $end = $s->end_date ? Carbon::parse($s->end_date)->startOfMonth() : $start->copy()->addMonths(12);
        return max(1, $start->diffInMonths($end) + 1);
    }

    public function runRecurring(Carbon $period, bool $dryRun = false): array
    {
        if (!Schema::hasTable('recurring_journals')) {
            return [0, 0.0];
        }
        $count = 0; $total = 0.0;
        $day = (int)$period->copy()->endOfMonth()->day; // clamp to month end
        $dateToPost = $period->copy()->startOfMonth();

        /** @var \App\Services\LedgerService $ledger */
        $ledger = app(\App\Services\LedgerService::class);

        $items = RecurringJournal::query()->where('active', true)->get();
        foreach ($items as $r) {
            $postDay = min(max(1, (int)$r->day_of_month), $day);
            $date = $dateToPost->copy()->day($postDay);
            $amount = (float)$r->amount;
            if ($amount <= 0) { continue; }

            $count++; $total += $amount;
            if ($dryRun) { continue; }

            try {
                if (method_exists($ledger, 'recordSimple')) {
                    $ledger->recordSimple(
                        [
                            'date' => $date->toDateString(),
                            'description' => 'Recurring: '.$r->name,
                            'reference' => ($r->reference_prefix ?: 'RJ').'-'.$date->format('Ymd').'-'.$r->id,
                        ],
                        (int)$r->debit_account_id,
                        (int)$r->credit_account_id,
                        (float)$amount
                    );
                }
                if (Schema::hasColumn('recurring_journals', 'last_posted_at')) {
                    $r->last_posted_at = Carbon::now();
                    $r->save();
                }
            } catch (\Throwable $e) {
                Log::error('Failed posting recurring journal', ['recurring_id' => $r->id, 'message' => $e->getMessage()]);
            }
        }

        return [$count, round($total, 2)];
    }
}
