<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Services\BankReconciliationService;
use Illuminate\Console\Command;

class BankReconcile extends Command
{
    protected $signature = 'bank:reconcile {account_id} {--from=} {--until=} {--tolerance=3} {--dry-run}';
    protected $description = 'Attempt automatic reconciliation of bank statement lines to ledger entries.';

    public function handle(BankReconciliationService $svc): int
    {
        if (!$svc->featureEnabled()) {
            $this->warn('Bank reconciliation feature disabled or tables missing. Nothing to do.');
            return self::SUCCESS;
        }

        $accountId = (int) $this->argument('account_id');
        $from = $this->option('from');
        $until = $this->option('until');
        $tol = (int) $this->option('tolerance');
        $dry = (bool) $this->option('dry-run');

        $account = BankAccount::query()->find($accountId);
        if (!$account) {
            $this->error('Bank account not found: ' . $accountId);
            return self::FAILURE;
        }

        $count = $svc->reconcileAuto($account, $from, $until, $tol, $dry);
        $this->info(($dry ? '[DRY RUN] ' : '') . "Auto-matched $count statement lines for account {$account->name}.");
        return self::SUCCESS;
    }
}
