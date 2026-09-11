<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class OptimizeDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:optimize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize the database tables to reclaim space (MySQL OPTIMIZE / PostgreSQL VACUUM)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = DB::getDefaultConnection();
        $driver = DB::connection($connection)->getConfig('driver');

        $this->info("Starting database optimization for driver: {$driver}");

        try {
            switch ($driver) {
                case 'mysql':
                case 'mariadb':
                    $this->optimizeMysql();
                    break;
                case 'pgsql':
                    $this->optimizePostgres();
                    break;
                case 'sqlite':
                    $this->optimizeSqlite();
                    break;
                default:
                    $this->warn("Optimization not implemented for driver: {$driver}");
                    return Command::SUCCESS;
            }
        } catch (Exception $e) {
            $this->error("Optimization failed: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info('Database optimization completed successfully.');
        return Command::SUCCESS;
    }

    /**
     * Optimize MySQL tables.
     */
    protected function optimizeMysql()
    {
        $tables = Schema::getTables();

        foreach ($tables as $table) {
            $tableName = $table['name'];
            $this->comment("Optimizing table: {$tableName}");

            // OPTIMIZE TABLE is safe and does not delete records.
            // It reclaims space from deleted rows and defragments the data file.
            DB::statement("OPTIMIZE TABLE `{$tableName}`");
        }
    }

    /**
     * Optimize PostgreSQL database.
     */
    protected function optimizePostgres()
    {
        $this->comment("Running VACUUM ANALYZE...");
        // VACUUM ANALYZE reclaims space and updates statistics.
        // Note: VACUUM cannot be run inside a transaction.
        // Laravel's DB::statement executes immediately.
        DB::statement('VACUUM ANALYZE');
    }

    /**
     * Optimize SQLite database.
     */
    protected function optimizeSqlite()
    {
        $this->comment("Running VACUUM...");
        // VACUUM rebuilds the database file, repacking it into a minimal amount of disk space.
        DB::statement('VACUUM');
    }
}
