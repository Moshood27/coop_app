<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Adjust these to match your environment if needed
// The error says host: db, port: 3306. 
// If I run this via 'sail php' or inside the container, it should use the .env settings.

try {
    echo "Checking attendance_records table structure:\n";
    $columns = DB::select("DESCRIBE attendance_records");
    foreach ($columns as $column) {
        echo sprintf(
            "%-20s | %-20s | %-5s | %-5s | %-10s | %s\n",
            $column->Field,
            $column->Type,
            $column->Null,
            $column->Key,
            $column->Default ?? 'NULL',
            $column->Extra
        );
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
