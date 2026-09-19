<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\LedgerEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    /**
     * Import statement lines from an array of rows.
     * Each row: ['date' => Y-m-d|d/m/Y, 'amount' => float, 'description' => string|null, 'reference' => string|null]
     */
    public function importLines(BankStatement $statement, array $rows): int
    {
        $count = 0;
        DB::transaction(function () use ($statement, $rows, &$count) {
            foreach ($rows as $row) {
                $dateStr = $row['date'] ?? $row['txn_date'] ?? null;
                $date = $this->parseDate($dateStr);
                $amount = (float) ($row['amount'] ?? 0);
                $desc = $row['description'] ?? null;
                $ref = $row['reference'] ?? null;
                $hash = sha1(($date?->toDateString() ?? '') . '|' . number_format($amount, 2, '.', '') . '|' . trim((string) $desc) . '|' . trim((string) $ref));

                BankStatementLine::updateOrCreate([
                    'bank_statement_id' => $statement->id,
                    'hash' => $hash,
                ], [
                    'txn_date' => $date?->toDateString(),
                    'amount' => $amount,
                    'description' => $desc,
                    'reference' => $ref,
                    'status' => 'unmatched',
                ]);

                $count++;
            }

            $statement->update(['status' => 'imported']);
        });

        return $count;
    }

    /**
     * Attempt to auto-match statement lines to ledger entries on the linked bank ledger account.
     * Matching by absolute amount and nearest date within +/- 7 days, optional reference/description contains.
     */
    public function autoMatch(BankStatement $statement, int $daysTolerance = 7): int
    {
        $bankLedgerId = optional($statement->bankAccount->ledgerAccount)->id;
        if (!$bankLedgerId) {
            return 0;
        }

        $matched = 0;
        DB::transaction(function () use ($statement, $daysTolerance, $bankLedgerId, &$matched) {
            $lines = $statement->lines()->where('status', 'unmatched')->get();
            foreach ($lines as $line) {
                $from = Carbon::parse($line->txn_date)->copy()->subDays($daysTolerance)->toDateString();
                $to = Carbon::parse($line->txn_date)->copy()->addDays($daysTolerance)->toDateString();
                $amount = abs((float) $line->amount);

                // Try debit side
                $entry = LedgerEntry::where('ledger_account_id', $bankLedgerId)
                    ->whereBetween('created_at', [$from, $to])
                    ->where(function ($q) use ($amount) {
                        $q->whereRaw('ABS(debit) = ?', [$amount])
                          ->orWhereRaw('ABS(credit) = ?', [$amount]);
                    })
                    ->orderByDesc('id')
                    ->first();

                if ($entry) {
                    $line->update([
                        'matched_journal_id' => $entry->ledger_journal_id,
                        'matched_entry_id' => $entry->id,
                        'status' => 'matched',
                    ]);
                    $matched++;
                }
            }
        });

        return $matched;
    }

    public function startReconciliation(BankAccount $account, string $from, string $to, float $endingBalance, ?int $userId = null): BankReconciliation
    {
        return BankReconciliation::create([
            'bank_account_id' => $account->id,
            'period_from' => $from,
            'period_to' => $to,
            'ending_balance' => $endingBalance,
            'status' => 'in_progress',
            'reconciled_by' => $userId,
        ]);
    }

    public function finalize(BankReconciliation $rec): BankReconciliation
    {
        $rec->update(['status' => 'finalized']);
        return $rec->fresh();
    }

    private function parseDate(?string $val): ?Carbon
    {
        if (!$val) return null;
        $val = trim($val);
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'] as $fmt) {
            $dt = Carbon::createFromFormat($fmt, $val);
            if ($dt !== false) return $dt;
        }
        try {
            return Carbon::parse($val);
        } catch (\Throwable) {
            return null;
        }
    }
}
