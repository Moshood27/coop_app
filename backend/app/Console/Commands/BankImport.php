<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Services\BankReconciliationService;
use Illuminate\Console\Command;

class BankImport extends Command
{
    protected $signature = 'bank:import {account_id} {file} {--delimiter=,} {--date-format=Y-m-d}';
    protected $description = 'Import a bank statement CSV into Bank Statements (schema/feature-aware).';

    public function handle(BankReconciliationService $svc): int
    {
        if (!$svc->featureEnabled()) {
            $this->warn('Bank reconciliation feature disabled or tables missing. Parsing CSV only (no persist).');
        }

        $accountId = (int) $this->argument('account_id');
        $file = (string) $this->argument('file');
        $delimiter = (string) $this->option('delimiter');
        $dateFormat = (string) $this->option('date-format');

        $account = BankAccount::query()->find($accountId);
        if (!$account) {
            $this->error('Bank account not found: ' . $accountId);
            return self::FAILURE;
        }

        [$statement, $count] = $svc->importCsv($account, $file, [
            'delimiter' => $delimiter,
            'date_format' => $dateFormat,
        ]);

        if ($statement) {
            $this->info("Imported $count lines into statement #{$statement->id} for account {$account->name}.");
        } else {
            $this->info("Parsed $count lines (not saved). Enable feature + run migrations to persist.");
        }

        return self::SUCCESS;
    }
}
