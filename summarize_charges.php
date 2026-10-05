<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

$results = WalletTransaction::select(
        DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
        'source',
        DB::raw('COUNT(*) as count'),
        DB::raw('SUM(amount) as total_amount')
    )
    ->whereIn('source', ['attendance_fine_collection', 'admin_charge'])
    ->where('type', 'debit')
    ->groupBy('month', 'source')
    ->orderBy('month', 'desc')
    ->get();

echo str_pad("Month", 10) . " | " . str_pad("Source", 30) . " | " . str_pad("Count", 8) . " | " . "Total Amount\n";
echo str_repeat("-", 70) . "\n";

foreach ($results as $row) {
    echo str_pad($row->month, 10) . " | " . 
         str_pad($row->source, 30) . " | " . 
         str_pad($row->count, 8) . " | " . 
         number_format($row->total_amount, 2) . "\n";
}
