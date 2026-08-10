<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $columns = Illuminate\Support\Facades\DB::getSchemaBuilder()->getColumnListing('contributions');
    echo "Columns in contributions table:\n";
    print_r($columns);

    $columnsRep = Illuminate\Support\Facades\DB::getSchemaBuilder()->getColumnListing('qard_hasan_repayments');
    echo "\nColumns in qard_hasan_repayments table:\n";
    print_r($columnsRep);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
