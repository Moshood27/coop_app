<?php

namespace App\Console\Commands;

use App\Services\FixedAssetService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixedAssetsDepreciate extends Command
{
    protected $signature = 'fixed-assets:depreciate {--period=} {--dry-run}';
    protected $description = 'Post monthly depreciation for fixed assets (feature/table aware).';

    public function handle(FixedAssetService $svc): int
    {
        $periodOpt = $this->option('period');
        $period = $periodOpt ? Carbon::parse($periodOpt)->startOfMonth() : Carbon::now()->startOfMonth();
        $dry = (bool) $this->option('dry-run');

        if (!$svc->featureEnabled()) {
            $this->warn('Fixed assets feature disabled or table missing. Nothing to do.');
            return self::SUCCESS;
        }

        [$count, $total, $journals] = $svc->postMonthlyDepreciation($period, $dry);
        $this->info(($dry ? '[DRY RUN] ' : '') . "Processed $count assets, total depreciation " . number_format($total, 2) . ", journals created: $journals");
        return self::SUCCESS;
    }
}
