<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\Schema;

class GeneralLedgerReportService
{
    /**
     * Build a detailed general ledger for one account with running balance.
     * Filters: date range and optional branch_id.
     */
    public function build(int $ledgerAccountId, string $fromDate, string $toDate, ?int $branchId = null): array
    {
        $account = LedgerAccount::findOrFail($ledgerAccountId);

        $sign = in_array(strtolower($account->type), ['asset', 'expense']) ? 1 : -1;

        $openingQuery = LedgerEntry::where('ledger_account_id', $ledgerAccountId)
            ->whereHas('journal', function ($q) use ($fromDate) {
                $q->where('date', '<', $fromDate);
            });
        if ($branchId !== null && Schema::hasColumn('ledger_entries', 'branch_id')) {
            $openingQuery->where('branch_id', $branchId);
        }
        $openingDebits = (float) $openingQuery->sum('debit');
        $openingCredits = (float) $openingQuery->sum('credit');
        $openingBalance = $sign * ($openingDebits - $openingCredits);

        $entriesQuery = LedgerEntry::with('journal')
            ->where('ledger_account_id', $ledgerAccountId)
            ->whereHas('journal', function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('date', [$fromDate, $toDate]);
            })
            ->orderByRelation('journal.date')
            ->orderBy('id');

        if ($branchId !== null && Schema::hasColumn('ledger_entries', 'branch_id')) {
            $entriesQuery->where('branch_id', $branchId);
        }

        $rows = [];
        $running = $openingBalance;
        foreach ($entriesQuery->get() as $e) {
            $running += $sign * ((float)$e->debit - (float)$e->credit);
            $rows[] = [
                'date' => optional($e->journal)->date?->toDateString(),
                'journal_id' => $e->journal_id,
                'journal_number' => optional($e->journal)->number,
                'reference' => optional($e->journal)->reference,
                'description' => $e->description ?: optional($e->journal)->description,
                'debit' => (float) $e->debit,
                'credit' => (float) $e->credit,
                'running_balance' => round($running, 2),
                'branch_id' => $e->branch_id,
            ];
        }

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'branch_id' => $branchId,
            ],
            'opening_balance' => round($openingBalance, 2),
            'entries' => $rows,
            'closing_balance' => count($rows) ? end($rows)['running_balance'] : round($openingBalance, 2),
        ];
    }
}
