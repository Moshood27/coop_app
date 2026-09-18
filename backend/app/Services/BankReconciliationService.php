<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\LedgerEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BankReconciliationService
{
    public function featureEnabled(): bool
    {
        if (!config('accounting.features.bank_reconciliation')) {
            return false;
        }
        return Schema::hasTable('bank_statements') && Schema::hasTable('bank_statement_lines');
    }

    /**
     * Import a simple CSV: date,description,amount,balance,reference
     * Returns [BankStatement|null, int importedCount].
     * If schema not present/feature off, parses rows and returns [null, count] without persisting.
     */
    public function importCsv(BankAccount $account, string $filePath, array $options = []): array
    {
        $delimiter = $options['delimiter'] ?? ',';
        $dateFormat = $options['date_format'] ?? 'Y-m-d';

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Unable to open file: $filePath");
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) < 3) {
                continue;
            }
            // Try to map columns in a flexible manner
            [$d, $desc, $amt, $bal, $ref] = array_pad($data, 5, null);
            $rows[] = [
                'date' => $this->parseDate($d, $dateFormat),
                'description' => trim((string)$desc),
                'amount' => (float)str_replace([','], '', (string)$amt),
                'balance' => is_null($bal) ? null : (float)str_replace([','], '', (string)$bal),
                'external_reference' => $ref ? trim((string)$ref) : null,
            ];
        }
        fclose($handle);

        if (!$this->featureEnabled()) {
            return [null, count($rows)];
        }

        return DB::transaction(function () use ($account, $rows) {
            $statement = BankStatement::create([
                'bank_account_id' => $account->id,
                'statement_date' => Carbon::now()->toDateString(),
                'opening_balance' => 0.0,
                'closing_balance' => 0.0,
                'currency' => $account->currency,
                'source' => 'csv',
                'reference' => 'import-'.uniqid(),
            ]);
            $count = 0;
            foreach ($rows as $r) {
                BankStatementLine::create(array_merge($r, [
                    'bank_statement_id' => $statement->id,
                ]));
                $count++;
            }
            return [$statement, $count];
        });
    }

    protected function parseDate($value, string $format): string
    {
        // Try common formats
        $try = [$format, 'Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'];
        foreach ($try as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, (string)$value);
                if ($c) return $c->toDateString();
            } catch (\Throwable $e) {
                // continue
            }
        }
        return Carbon::parse((string)$value)->toDateString();
    }

    /**
     * Attempt automatic matching by amount (abs) and date tolerance against the bank GL account.
     * Returns number of matches created. If feature/tables missing, returns 0.
     */
    public function reconcileAuto(BankAccount $account, ?string $from = null, ?string $until = null, int $toleranceDays = 3, bool $dryRun = false): int
    {
        if (!$this->featureEnabled()) {
            return 0;
        }
        if (!$account->gl_account_id) {
            Log::warning('Bank account has no linked GL control account; cannot auto-reconcile.', ['bank_account_id' => $account->id]);
            return 0;
        }

        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subMonths(6)->startOfDay();
        $untilDate = $until ? Carbon::parse($until)->endOfDay() : Carbon::now()->endOfDay();

        $lines = BankStatementLine::query()
            ->whereHas('statement', function ($q) use ($account) {
                $q->where('bank_account_id', $account->id);
            })
            ->where(function ($q) use ($fromDate, $untilDate) {
                $q->whereDate('date', '>=', $fromDate->toDateString())
                  ->whereDate('date', '<=', $untilDate->toDateString());
            })
            ->where(function ($q) {
                if (Schema::hasColumn('bank_statement_lines', 'is_matched')) {
                    $q->where(function ($q2) {
                        $q2->whereNull('is_matched')->orWhere('is_matched', false);
                    });
                } else {
                    $q->whereNull('matched_ledger_entry_id');
                }
            })
            ->orderBy('date')
            ->limit(1000)
            ->get();

        $matched = 0;
        foreach ($lines as $line) {
            $amount = abs((float)$line->amount);
            $date = Carbon::parse($line->date);

            $candidate = LedgerEntry::query()
                ->where('ledger_account_id', $account->gl_account_id)
                ->where(function ($q) use ($amount) {
                    $q->where('debit', $amount)->orWhere('credit', $amount);
                })
                ->whereHas('journal', function ($q) use ($date, $toleranceDays) {
                    $q->whereDate('date', '>=', $date->copy()->subDays($toleranceDays)->toDateString())
                      ->whereDate('date', '<=', $date->copy()->addDays($toleranceDays)->toDateString());
                })
                ->orderByDesc('id')
                ->first();

            if ($candidate) {
                if (!$dryRun) {
                    if (Schema::hasColumn('bank_statement_lines', 'matched_ledger_entry_id')) {
                        $line->matched_ledger_entry_id = $candidate->id;
                    }
                    if (Schema::hasColumn('bank_statement_lines', 'is_matched')) {
                        $line->is_matched = true;
                    }
                    $line->save();
                }
                $matched++;
            }
        }

        return $matched;
    }
}
