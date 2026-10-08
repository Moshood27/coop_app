<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SyncToR2Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:sync-r2';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform incremental sync of local uploads to R2 using rclone';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting incremental R2 sync via rclone...');

        $scriptPath = base_path('scripts/backup-incremental.sh');

        if (!file_exists($scriptPath)) {
            $this->error("Backup script not found at {$scriptPath}");
            return 1;
        }

        $process = Process::timeout(3600) // 1 hour timeout for large syncs
            ->path(base_path())
            ->run(['bash', 'scripts/backup-incremental.sh']);

        if ($process->successful()) {
            $this->info($process->output());
            $this->info('Incremental R2 sync completed successfully.');
            return 0;
        }

        $this->error('R2 sync failed:');
        $this->error($process->errorOutput());
        return 1;
    }
}
