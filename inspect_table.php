<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "SHOW CREATE TABLE attendance_records:\n";
    $result = DB::select("SHOW CREATE TABLE attendance_records");
    $createTable = (array)$result[0];
    echo $createTable['Create Table'] . "\n\n";

    echo "Record count: " . DB::table('attendance_records')->count() . "\n";
    
    echo "\nChecking for other tables without primary keys:\n";
    $tables = DB::select("SHOW TABLES");
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        $pk = DB::select("SHOW KEYS FROM {$tableName} WHERE Key_name = 'PRIMARY'");
        if (empty($pk)) {
            echo "Table {$tableName} has NO PRIMARY KEY!\n";
        }
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
