<?php

namespace App\Console\Commands;

use App\Services\MonthlyBalanceService;
use Illuminate\Console\Command;

class RebuildMonthlyBalances extends Command
{
    protected $signature = 'accounting:rebuild-monthly-balances {--from=} {--to=} {--branch=} {--truncate}';
    protected $description = 'Recompute ledger_account_monthly_balances for a date range (and optional branch).';

    public function handle(MonthlyBalanceService $svc): int
    {
        $from = $this->option('from') ?: null;
        $to = $this->option('to') ?: null;
        $branch = $this->option('branch') ? (int) $this->option('branch') : null;
        $truncate = (bool) $this->option('truncate');

        $res = $svc->rebuild($from, $to, $branch, $truncate);
        $this->info('Months processed: ' . $res['months']);
        $this->info('Rows inserted: ' . $res['rows']);

        return self::SUCCESS;
    }
}
