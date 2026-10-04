<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $tablesToInspect = ['attendance_records', 'investment_record_details', 'loan', 'members', 'units'];
    foreach ($tablesToInspect as $tableName) {
        echo "--------------------------------------------------\n";
        echo "Checking table {$tableName}:\n";
        
        $duplicateIds = DB::table($tableName)
            ->select('id', DB::raw('COUNT(*) as count'))
            ->groupBy('id')
            ->having('count', '>', 1)
            ->get();
            
        if ($duplicateIds->count() > 0) {
            echo "WARNING: Table {$tableName} HAS DUPLICATE IDs!\n";
            foreach ($duplicateIds as $dup) {
                echo "  ID {$dup->id} appears {$dup->count} times\n";
            }
        } else {
            echo "Table {$tableName} has unique IDs.\n";
        }

        $maxId = DB::table($tableName)->max('id');
        echo "Max ID: " . ($maxId ?? 'NULL') . "\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
