<?php

namespace App\Console\Commands;

use App\Services\AccrualService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunAccruals extends Command
{
    protected $signature = 'accruals:run {--period=} {--dry-run}';
    protected $description = 'Post monthly accruals/deferrals for active schedules (feature/table aware).';

    public function handle(AccrualService $svc): int
    {
        if (!$svc->featureEnabled()) {
            $this->warn('Accruals/deferrals feature disabled or tables missing. Nothing to do.');
            return self::SUCCESS;
        }
        $periodOpt = $this->option('period');
        $period = $periodOpt ? Carbon::parse($periodOpt)->startOfMonth() : Carbon::now()->startOfMonth();
        $dry = (bool) $this->option('dry-run');

        [$count, $total] = $svc->postMonthlyAccruals($period, $dry);
        $this->info(($dry ? '[DRY RUN] ' : '') . "Posted $count accrual(s), total " . number_format($total, 2));
        return self::SUCCESS;
    }
}
