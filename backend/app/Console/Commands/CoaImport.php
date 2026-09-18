<?php

namespace App\Console\Commands;

use App\Services\CoaImportExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CoaImport extends Command
{
    protected $signature = 'coa:import {path : Input CSV file path} {--dry-run : Validate and simulate without writing changes}';
    protected $description = 'Import Chart of Accounts from a CSV file';

    public function handle(): int
    {
        $path = $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        if (!file_exists($path)) {
            $this->error("File not found: $path");
            return self::FAILURE;
        }

        $csv = (string) file_get_contents($path);
        if ($csv === '') {
            $this->error('Input file is empty.');
            return self::FAILURE;
        }

        $service = app(CoaImportExportService::class);

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $results = $service->importFromCsv($csv);
                DB::rollBack();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error('Dry-run failed: ' . $e->getMessage());
                return self::FAILURE;
            }
            $created = $results->where('action', 'created')->count();
            $updated = $results->where('action', 'updated')->count();
            $this->info("Dry-run OK. Would create: $created, update: $updated");
            return self::SUCCESS;
        }

        try {
            $results = $service->importFromCsv($csv);
        } catch (\Throwable $e) {
            $this->error('Import failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $created = $results->where('action', 'created')->count();
        $updated = $results->where('action', 'updated')->count();
        $this->info("Import complete. Created: $created, Updated: $updated");
        return self::SUCCESS;
    }
}
