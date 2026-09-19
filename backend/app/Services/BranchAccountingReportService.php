<?php

namespace App\Services;

use App\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;

class BranchAccountingReportService
{
    /**
     * Build a simple trial balance scoped to a branch using `ledger_entries.branch_id`.
     * Returns array: ['accounts' => [...], 'totals' => ['debit' => x, 'credit' => y]]
     */
    public function buildTrialBalanceByBranch(int $branchId, ?string $from = null, ?string $to = null): array
    {
        $query = DB::table('ledger_entries as le')
            ->join('ledger_accounts as la', 'la.id', '=', 'le.ledger_account_id')
            ->select('le.ledger_account_id', 'la.name', 'la.code', 'la.type',
                DB::raw('SUM(le.debit) as total_debit'),
                DB::raw('SUM(le.credit) as total_credit'))
            ->where('le.branch_id', $branchId)
            ->groupBy('le.ledger_account_id', 'la.name', 'la.code', 'la.type')
            ->orderBy('la.code');

        if ($from) {
            $query->whereDate('le.created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('le.created_at', '<=', $to);
        }

        $rows = $query->get();

        $accounts = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($rows as $row) {
            $balance = $this->normalBalance((string)$row->type, (float)$row->total_debit, (float)$row->total_credit);
            $dr = $cr = 0.0;
            if (in_array($row->type, ['asset', 'expense'])) {
                $dr = max(0, $balance);
                $cr = max(0, -$balance);
            } else {
                $cr = max(0, $balance);
                $dr = max(0, -$balance);
            }
            $accounts[] = [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'debit' => round($dr, 2),
                'credit' => round($cr, 2),
            ];
            $totalDebit += $dr;
            $totalCredit += $cr;
        }

        return [
            'accounts' => $accounts,
            'totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
            ],
            'branch_id' => $branchId,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function normalBalance(string $type, float $debits, float $credits): float
    {
        if (in_array($type, ['asset', 'expense'])) {
            return $debits - $credits;
        }
        return $credits - $debits;
    }
}
