<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Scheduled tasks are now defined in routes/console.php
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        Artisan::starting(function ($artisan) {
            $artisan->resolveCommands([
                \App\Console\Commands\CollectAdministrativeCharges::class,
                \App\Console\Commands\ProcessAdministrativeCharges::class,
                \App\Console\Commands\VerifyTrialBalance::class,
                \App\Console\Commands\CoaExport::class,
                \App\Console\Commands\CoaImport::class,
                \App\Console\Commands\BankImport::class,
                \App\Console\Commands\BankReconcile::class,
                \App\Console\Commands\FixedAssetsDepreciate::class,
                \App\Console\Commands\RunAccruals::class,
                \App\Console\Commands\RunRecurringJournals::class,
            ]);
        });

        require base_path('routes/console.php');
    }
}
