<?php

namespace App\Console\Commands;

use App\Services\AccrualService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunRecurringJournals extends Command
{
    protected $signature = 'journals:recurring:run {--period=} {--dry-run}';
    protected $description = 'Post recurring journals for active templates (feature/table aware).';

    public function handle(AccrualService $svc): int
    {
        if (!$svc->featureEnabled()) {
            $this->warn('Accruals/deferrals feature disabled or tables missing. Nothing to do.');
            return self::SUCCESS;
        }
        $periodOpt = $this->option('period');
        $period = $periodOpt ? Carbon::parse($periodOpt)->startOfMonth() : Carbon::now()->startOfMonth();
        $dry = (bool) $this->option('dry-run');

        [$count, $total] = $svc->runRecurring($period, $dry);
        $this->info(($dry ? '[DRY RUN] ' : '') . "Posted $count recurring journal(s), total " . number_format($total, 2));
        return self::SUCCESS;
    }
}
