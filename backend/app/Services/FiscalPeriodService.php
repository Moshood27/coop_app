<?php

namespace App\Services;

use App\Models\PostingPeriod;
use App\Support\Accounting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class FiscalPeriodService
{
    /**
     * Returns true if the given date is within an open posting period.
     * If fiscal period tables do not exist or feature is disabled, it returns true.
     */
    public function isDateInOpenPeriod(CarbonInterface|string|null $date): bool
    {
        if (!Accounting::guard('enforce_open_period')) {
            return true;
        }

        // Only enforce if tables are available (or explicitly marked as applied)
        $hasSchema = Accounting::tableExists('posting_periods');
        if (!$hasSchema && !Accounting::migrationApplied('fiscal_periods')) {
            return true;
        }

        $d = $date ? Carbon::parse($date) : now();

        return PostingPeriod::query()
            ->where('start_date', '<=', $d->toDateString())
            ->where('end_date', '>=', $d->toDateString())
            ->where('is_open', true)
            ->exists();
    }

    /**
     * Throws if date is not in an open period (when enforcement is active and schema exists).
     */
    public function assertDateInOpenPeriod(CarbonInterface|string|null $date): void
    {
        if (!$this->isDateInOpenPeriod($date)) {
            throw new \RuntimeException('Posting date is not within an open fiscal period.');
        }
    }

    /**
     * Year-end closing stub: when enabled and schema exists, compute net income
     * for the year and create a closing journal moving P&L to Retained Earnings.
     * No-op if schema/tables are missing. Returns created journal or null.
     */
    public function closeYear(\App\Models\FiscalYear $year): ?\App\Models\LedgerJournal
    {
        if (!Accounting::feature('fiscal_periods') || !Accounting::tableExists('fiscal_years')) {
            return null;
        }

        // Prevent double-close if column exists and is_closed is true
        if (\App\Support\Accounting::columnExists('fiscal_years', 'is_closed') && (bool) $year->is_closed) {
            return null;
        }

        // Compute P&L: sum income - expenses for the year
        $from = Carbon::parse($year->start_date)->startOfDay();
        $until = Carbon::parse($year->end_date)->endOfDay();

        if (!Accounting::tableExists('ledger_entries') || !Accounting::tableExists('ledger_accounts') || !Accounting::tableExists('ledger_journals')) {
            return null;
        }

        $incomeAccountIds = \App\Models\LedgerAccount::where('type', 'income')->pluck('id');
        $expenseAccountIds = \App\Models\LedgerAccount::where('type', 'expense')->pluck('id');

        $income = (float) \App\Models\LedgerEntry::whereIn('ledger_account_id', $incomeAccountIds)
            ->whereHas('journal', fn($q) => $q->whereBetween('date', [$from, $until]))
            ->selectRaw('COALESCE(SUM(credit - debit),0) as amt')
            ->value('amt');

        $expenses = (float) \App\Models\LedgerEntry::whereIn('ledger_account_id', $expenseAccountIds)
            ->whereHas('journal', fn($q) => $q->whereBetween('date', [$from, $until]))
            ->selectRaw('COALESCE(SUM(debit - credit),0) as amt')
            ->value('amt');

        $netIncome = round($income - $expenses, 2);

        if (abs($netIncome) < 0.005) {
            // Nothing to do
            return null;
        }

        // Retained Earnings account code default (can adjust later)
        $retainedCode = '3200';

        // Build closing journal entries: close out income/expense to retained earnings
        $entries = [];
        if ($netIncome > 0) {
            // Profit: Dr Income Summary, Cr Retained Earnings
            $entries[] = ['code' => '3999', 'debit' => $netIncome, 'description' => 'Close Income to Retained'];
            $entries[] = ['code' => $retainedCode, 'credit' => $netIncome, 'description' => 'Transfer Net Income'];
        } else {
            // Loss: Dr Retained Earnings, Cr Income Summary
            $loss = abs($netIncome);
            $entries[] = ['code' => $retainedCode, 'debit' => $loss, 'description' => 'Transfer Net Loss'];
            $entries[] = ['code' => '3999', 'credit' => $loss, 'description' => 'Close Income to Retained'];
        }

        try {
            $journal = app(\App\Services\LedgerService::class)->recordByCode([
                'date' => $year->end_date ?? now(),
                'reference' => 'YE-CLOSE-' . $year->id,
                'description' => 'Year-end closing transfer to Retained Earnings',
            ], $entries);

            // Optionally mark year as closed
            if (\App\Support\Accounting::columnExists('fiscal_years', 'is_closed')) {
                $year->forceFill(['is_closed' => true])->saveQuietly();
            }

            // Auto-post if supported
            app(\App\Services\LedgerService::class)->postJournal($journal);
            return $journal;
        } catch (\Throwable) {
            return null;
        }
    }
}
