<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contribution;
use Illuminate\Support\Facades\DB;

class FixContributionDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contributions:fix-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate missing paid_at dates from created_at for successful contributions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Contribution::where('status', 'success')
            ->whereNull('paid_at')
            ->count();

        if ($count === 0) {
            $this->info('No records to fix.');
            return 0;
        }

        $this->info("Found {$count} records to fix.");

        // We use update with DB::raw to be efficient
        $affected = Contribution::where('status', 'success')
            ->whereNull('paid_at')
            ->update(['paid_at' => DB::raw('created_at')]);

        $this->info("Successfully updated {$affected} records.");

        return 0;
    }
}
