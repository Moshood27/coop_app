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
        echo "SHOW CREATE TABLE {$tableName}:\n";
        try {
            $result = DB::select("SHOW CREATE TABLE {$tableName}");
            $createTable = (array)$result[0];
            echo $createTable['Create Table'] . "\n\n";
            echo "Record count: " . DB::table($tableName)->count() . "\n";
        } catch (\Throwable $e) {
            echo "Error inspecting {$tableName}: " . $e->getMessage() . "\n";
        }
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
