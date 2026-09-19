<?php

namespace App\Services;

use App\Models\LedgerAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonthlyBalanceService
{
    public function isAvailable(): bool
    {
        return Schema::hasTable('ledger_account_monthly_balances')
            && Schema::hasTable('ledger_entries')
            && Schema::hasTable('ledger_journals')
            && Schema::hasTable('ledger_accounts');
    }

    /**
     * Rebuild monthly balances for the given date range.
     *
     * @param string|null $from YYYY-MM-DD (inclusive). If null, uses earliest journal date.
     * @param string|null $to YYYY-MM-DD (inclusive). If null, uses today.
     * @param int|null $branchId Optional branch filter.
     * @param bool $truncate If true, clears existing rows in the target range before insert.
     * @return array Summary counts
     */
    public function rebuild(?string $from = null, ?string $to = null, ?int $branchId = null, bool $truncate = false): array
    {
        if (!$this->isAvailable()) {
            return ['months' => 0, 'rows' => 0];
        }

        $minDate = DB::table('ledger_journals')->min('date');
        $fromDate = $from ? Carbon::parse($from)->startOfMonth() : Carbon::parse($minDate ?: now())->startOfMonth();
        $toDate = $to ? Carbon::parse($to)->endOfMonth() : Carbon::now()->endOfMonth();

        $months = [];
        $cursor = $fromDate->copy();
        while ($cursor <= $toDate) {
            $months[] = [$cursor->year, $cursor->month];
            $cursor->addMonth()->startOfMonth();
        }

        $rowsInserted = 0;

        foreach ($months as [$year, $month]) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();

            if ($truncate) {
                DB::table('ledger_account_monthly_balances')
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('year', $year)->where('month', $month)
                    ->delete();
            }

            // Calculate opening balances up to day before start
            $opening = DB::table('ledger_entries')
                ->join('ledger_journals', 'ledger_entries.ledger_journal_id', '=', 'ledger_journals.id')
                ->join('ledger_accounts', 'ledger_entries.ledger_account_id', '=', 'ledger_accounts.id')
                ->when(Schema::hasColumn('ledger_journals', 'status'), function ($q) {
                    $q->where('ledger_journals.status', 'posted');
                })
                ->when($branchId, fn($q) => $q->where('ledger_entries.branch_id', $branchId))
                ->where('ledger_journals.date', '<', $start->toDateString())
                ->groupBy('ledger_entries.ledger_account_id', 'ledger_accounts.type')
                ->select(
                    'ledger_entries.ledger_account_id as account_id',
                    'ledger_accounts.type as type',
                    DB::raw('SUM(ledger_entries.debit) as deb'),
                    DB::raw('SUM(ledger_entries.credit) as cre')
                )->get()->keyBy('account_id');

            // Movements within month
            $mov = DB::table('ledger_entries')
                ->join('ledger_journals', 'ledger_entries.ledger_journal_id', '=', 'ledger_journals.id')
                ->join('ledger_accounts', 'ledger_entries.ledger_account_id', '=', 'ledger_accounts.id')
                ->when(Schema::hasColumn('ledger_journals', 'status'), function ($q) {
                    $q->where('ledger_journals.status', 'posted');
                })
                ->when($branchId, fn($q) => $q->where('ledger_entries.branch_id', $branchId))
                ->whereBetween('ledger_journals.date', [$start->toDateString(), $end->toDateString()])
                ->groupBy('ledger_entries.ledger_account_id', 'ledger_accounts.type')
                ->select(
                    'ledger_entries.ledger_account_id as account_id',
                    'ledger_accounts.type as type',
                    DB::raw('SUM(ledger_entries.debit) as deb'),
                    DB::raw('SUM(ledger_entries.credit) as cre')
                )->get();

            foreach ($mov as $row) {
                $accId = (int) $row->account_id;
                $type = $row->type;
                $open = $opening->get($accId);
                $openDeb = $open->deb ?? 0.0; $openCre = $open->cre ?? 0.0;
                // Natural balance computation
                $openingBalance = in_array($type, ['asset', 'expense'])
                    ? (float) ($openDeb - $openCre)
                    : (float) ($openCre - $openDeb);

                $periodDeb = (float) $row->deb;
                $periodCre = (float) $row->cre;
                $movement = in_array($type, ['asset', 'expense'])
                    ? ($periodDeb - $periodCre)
                    : ($periodCre - $periodDeb);

                $closingBalance = (float) ($openingBalance + $movement);

                DB::table('ledger_account_monthly_balances')->insert([
                    'ledger_account_id' => $accId,
                    'year' => $year,
                    'month' => $month,
                    'branch_id' => $branchId,
                    'opening_balance' => round($openingBalance, 2),
                    'debits' => round($periodDeb, 2),
                    'credits' => round($periodCre, 2),
                    'closing_balance' => round($closingBalance, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $rowsInserted++;
            }
        }

        return ['months' => count($months), 'rows' => $rowsInserted];
    }
}
