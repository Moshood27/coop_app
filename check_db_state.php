<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$tables = ['meetings', 'attendance_records', 'branch_meeting', 'branches'];
foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        echo "Table '$table' exists.\n";
        // Check columns
        $columns = Schema::getColumnListing($table);
        echo "Columns: " . implode(', ', $columns) . "\n";
    } else {
        echo "Table '$table' does not exist.\n";
    }
}

$migrations = DB::table('migrations')->get();
echo "\nMigrations in DB:\n";
foreach ($migrations as $m) {
    echo "- " . $m->migration . "\n";
}
