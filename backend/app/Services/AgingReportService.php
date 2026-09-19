<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AgingReportService
{
    /**
     * Build a simple AR/AP aging by ledger account as of a date.
     * Note: This approximates aging by allocating net balance by entry date buckets.
     * A true invoice-level aging requires invoice/settlement linking, which may not exist.
     */
    public function build(string $type, string $asOfDate, array $buckets, ?int $branchId = null, ?array $accountCodes = null): array
    {
        if (!Schema::hasTable('ledger_entries') || !Schema::hasTable('ledger_accounts') || !Schema::hasTable('ledger_journals')) {
            return [
                'ready' => false,
                'reason' => 'Required ledger tables are missing. Run migrations after deployment.',
            ];
        }

        $asOf = Carbon::parse($asOfDate ?? now());
        sort($buckets);

        // Select candidate accounts: receivables (assets) for AR, payables (liabilities) for AP
        $accountsQuery = DB::table('ledger_accounts')->select('id', 'code', 'name', 'type');
        if ($accountCodes && count($accountCodes)) {
            $accountsQuery->whereIn('code', $accountCodes);
        } else {
            if ($type === 'ar') {
                $accountsQuery->where('type', 'asset');
            } elseif ($type === 'ap') {
                $accountsQuery->where('type', 'liability');
            }
        }
        $accounts = $accountsQuery->get();

        $results = [];
        $totals = array_fill_keys($this->bucketLabels($buckets), 0.0);
        $totals['total'] = 0.0;

        foreach ($accounts as $acc) {
            // Pull entries up to as-of
            $q = DB::table('ledger_entries as le')
                ->join('ledger_journals as lj', 'lj.id', '=', 'le.ledger_journal_id')
                ->where('le.ledger_account_id', $acc->id)
                ->whereDate('lj.date', '<=', $asOf->toDateString());
            if ($branchId && Schema::hasColumn('ledger_entries', 'branch_id')) {
                $q->where('le.branch_id', $branchId);
            }
            $entries = $q->select('le.debit', 'le.credit', 'lj.date')->get();

            if ($entries->isEmpty()) {
                continue;
            }

            $bucketsData = array_fill_keys($this->bucketLabels($buckets), 0.0);
            $accountTotal = 0.0;

            foreach ($entries as $e) {
                $net = ((float)$e->debit) - ((float)$e->credit);
                // For AP (liability), we take credit balances as positive exposure
                if ($type === 'ap') {
                    $net = -$net; // invert sign: credit exposure positive
                }
                if (abs($net) < 0.0000001) continue;

                $days = Carbon::parse($e->date)->diffInDays($asOf, false);
                $label = $this->classify($days, $buckets);
                $bucketsData[$label] += $net;
                $accountTotal += $net;
            }

            // Skip zero balance accounts
            if (abs($accountTotal) < 0.0001) {
                continue;
            }

            $results[] = [
                'account_id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'buckets' => $bucketsData,
                'total' => $accountTotal,
            ];

            foreach ($bucketsData as $k => $v) {
                $totals[$k] += $v;
            }
            $totals['total'] += $accountTotal;
        }

        return [
            'ready' => true,
            'as_of' => $asOf->toDateString(),
            'type' => $type,
            'buckets' => $this->bucketLabels($buckets),
            'rows' => $results,
            'totals' => $totals,
        ];
    }

    public function toCsv(array $aging): string
    {
        $lines = [];
        $headers = ['Account Code', 'Account Name'];
        foreach ($aging['buckets'] as $b) { $headers[] = $b; }
        $headers[] = 'Total';
        $lines[] = implode(',', $headers);

        foreach ($aging['rows'] as $row) {
            $line = [$row['code'], $row['name']];
            foreach ($aging['buckets'] as $b) { $line[] = number_format((float)($row['buckets'][$b] ?? 0), 2, '.', ''); }
            $line[] = number_format((float)$row['total'], 2, '.', '');
            $lines[] = implode(',', $line);
        }

        $totalLine = ['TOTAL', ''];
        foreach ($aging['buckets'] as $b) { $totalLine[] = number_format((float)($aging['totals'][$b] ?? 0), 2, '.', ''); }
        $totalLine[] = number_format((float)($aging['totals']['total'] ?? 0), 2, '.', '');
        $lines[] = implode(',', $totalLine);

        return implode("\r\n", $lines) . "\r\n";
    }

    private function bucketLabels(array $buckets): array
    {
        $labels = [];
        $prev = 0;
        foreach ($buckets as $b) {
            $labels[] = ($prev + 0) . '-' . $b;
            $prev = $b;
        }
        $labels[] = ($prev + 1) . '+';
        return $labels;
    }

    private function classify(int $days, array $buckets): string
    {
        $days = max(0, $days);
        $prev = 0;
        foreach ($buckets as $b) {
            if ($days <= $b) return ($prev + 0) . '-' . $b;
            $prev = $b;
        }
        return ($prev + 1) . '+';
    }
}
