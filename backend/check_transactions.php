<?php

use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking for Admin Charge Transactions:\n";

$transactions = WalletTransaction::where('source', 'admin_charge')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

if ($transactions->isEmpty()) {
    echo "No admin charge transactions found.\n";
} else {
    foreach ($transactions as $tx) {
        echo "TX ID: {$tx->id} - User ID: {$tx->user_id} - Amount: {$tx->amount} - Date: {$tx->created_at} - Description: " . ($tx->meta['description'] ?? 'N/A') . "\n";
    }
}

$totalCount = WalletTransaction::where('source', 'admin_charge')->count();
echo "\nTotal Admin Charge Transactions: $totalCount\n";

$lastRun = WalletTransaction::where('source', 'admin_charge')->max('created_at');
echo "Last successful run (based on transactions): " . ($lastRun ?: 'Never') . "\n";
