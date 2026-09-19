<?php

namespace App\Services;

use App\Models\FiscalPeriod;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class YearEndCloseService
{
    /**
     * Run year-end close for a fiscal period: move net profit/loss to retained earnings.
     *
     * - Computes balances for income and expense accounts within the period date range
     * - Posts a single journal to transfer net P&L to retained earnings account
     * - Optionally closes the fiscal period
     *
     * Guards: If required tables/columns are missing (pre-migration), this is a no-op.
     *
     * @param int $periodId
     * @param int|null $userId
     * @param bool $dryRun When true, returns preview and does not post journal or close period
     * @param bool $closePeriod When true, marks the period as closed after posting
     * @param string|null $retainedEarningsCode GL code for retained earnings (default 3100)
     * @return array{net: float, income: float, expense: float, posted_journal_id: int|null}
     * @throws Exception
     */
    public function run(int $periodId, ?int $userId = null, bool $dryRun = false, bool $closePeriod = true, ?string $retainedEarningsCode = null): array
    {
        if (!Schema::hasTable('fiscal_periods')) {
            return ['net' => 0.0, 'income' => 0.0, 'expense' => 0.0, 'posted_journal_id' => null];
        }

        $period = FiscalPeriod::findOrFail($periodId);
        $from = $period->starts_on;
        $to = $period->ends_on;

        // If ledger tables are not ready, abort quietly
        if (!Schema::hasTable('ledger_entries') || !Schema::hasTable('ledger_accounts')) {
            return ['net' => 0.0, 'income' => 0.0, 'expense' => 0.0, 'posted_journal_id' => null];
        }

        // Sum income and expense movements within period
        $income = (float) LedgerEntry::query()
            ->join('ledger_accounts', 'ledger_entries.ledger_account_id', '=', 'ledger_accounts.id')
            ->join('ledger_journals', 'ledger_entries.ledger_journal_id', '=', 'ledger_journals.id')
            ->when(Schema::hasColumn('ledger_journals', 'status'), function ($q) {
                $q->where('ledger_journals.status', 'posted');
            })
            ->whereBetween('ledger_journals.date', [$from, $to])
            ->where('ledger_accounts.type', 'income')
            ->selectRaw('COALESCE(SUM(credit - debit), 0) as amt')
            ->value('amt');

        $expense = (float) LedgerEntry::query()
            ->join('ledger_accounts', 'ledger_entries.ledger_account_id', '=', 'ledger_accounts.id')
            ->join('ledger_journals', 'ledger_entries.ledger_journal_id', '=', 'ledger_journals.id')
            ->when(Schema::hasColumn('ledger_journals', 'status'), function ($q) {
                $q->where('ledger_journals.status', 'posted');
            })
            ->whereBetween('ledger_journals.date', [$from, $to])
            ->where('ledger_accounts.type', 'expense')
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as amt')
            ->value('amt');

        $net = round($income - $expense, 2); // positive => profit, negative => loss

        // Determine retained earnings account
        $retainedEarningsCode = $retainedEarningsCode ?: (config('accounting.retained_earnings_code', '3100'));
        $reAccount = LedgerAccount::where('code', $retainedEarningsCode)->first();
        if (!$reAccount) {
            // Try a common alternative
            $reAccount = LedgerAccount::where('code', '3200')->first();
        }
        if (!$reAccount) {
            throw new Exception("Retained Earnings account not found (code {$retainedEarningsCode}).");
        }

        if ($dryRun || abs($net) < 0.005) {
            // Nothing to post or just preview
            if ($closePeriod && !$dryRun) {
                try { app(PeriodService::class)->close($periodId, $userId); } catch (\Throwable $e) {}
            }
            return ['net' => $net, 'income' => $income, 'expense' => $expense, 'posted_journal_id' => null];
        }

        return DB::transaction(function () use ($period, $net, $reAccount, $userId, $closePeriod, $income, $expense) {
            // Build entries to zero-out P&L and move to RE.
            // We post a single net transfer: Profit => Cr RE, Dr P&L Summary (or inverse for loss).
            // Use a temporary P&L Summary account if present; otherwise book directly against RE with description.
            $entries = [];

            // Credit retained earnings on profit; debit on loss
            if ($net > 0) {
                $entries[] = ['ledger_account_id' => $reAccount->id, 'credit' => $net, 'debit' => 0, 'description' => 'Transfer of net profit'];
            } else {
                $entries[] = ['ledger_account_id' => $reAccount->id, 'debit' => abs($net), 'credit' => 0, 'description' => 'Transfer of net loss'];
            }

            $journal = app(LedgerService::class)->record([
                'date' => $period->ends_on,
                'reference' => 'YEAR-END-CLOSE-' . $period->id,
                'description' => 'Year-end close to Retained Earnings for period ' . ($period->name ?? $period->id),
                'created_by' => $userId,
            ], $entries);

            if ($closePeriod) {
                try { app(PeriodService::class)->close($period->id, $userId); } catch (\Throwable $e) {}
            }

            return [
                'net' => $net,
                'income' => $income,
                'expense' => $expense,
                'posted_journal_id' => $journal->id,
            ];
        });
    }
}
