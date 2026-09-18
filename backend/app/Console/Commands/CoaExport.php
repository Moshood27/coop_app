<?php

namespace App\Console\Commands;

use App\Services\CoaImportExportService;
use Illuminate\Console\Command;

class CoaExport extends Command
{
    protected $signature = 'coa:export {path : Output CSV file path} {--include-inactive : Include inactive accounts}';
    protected $description = 'Export Chart of Accounts to a CSV file';

    public function handle(): int
    {
        $path = $this->argument('path');
        $includeInactive = (bool) $this->option('include-inactive');

        $csv = app(CoaImportExportService::class)->exportToCsv();
        if (!$includeInactive) {
            // Filter out inactive rows by re-parsing; since service doesn’t support filtering, do quick filter here.
            $rows = array_map('str_getcsv', explode("\n", trim($csv)));
            $header = array_map('strtolower', $rows[0] ?? []);
            $map = array_flip($header);
            $filtered = [];
            $filtered[] = $rows[0];
            for ($i = 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                if (!$r || count($r) === 1) { continue; }
                $isActive = isset($map['is_active']) ? (int)($r[$map['is_active']] ?? 1) : 1;
                if ($isActive === 1) { $filtered[] = $r; }
            }
            // Rebuild CSV
            $fp = fopen('php://temp', 'r+');
            foreach ($filtered as $row) { fputcsv($fp, $row); }
            rewind($fp);
            $csv = stream_get_contents($fp) ?: '';
        }

        if (@file_put_contents($path, $csv) === false) {
            $this->error("Failed to write file: $path");
            return self::FAILURE;
        }
        $this->info("COA exported to: $path");
        return self::SUCCESS;
    }
}
