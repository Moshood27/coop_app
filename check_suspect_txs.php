<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WalletTransaction;
use App\Models\User;
use App\Models\Setting;
use Carbon\Carbon;

$startDate = '2026-10-01'; // Assuming the issue was recent

$txs = WalletTransaction::whereIn('source', ['attendance_fine_collection', 'admin_charge'])
    ->where('type', 'debit')
    ->where('created_at', '>=', $startDate)
    ->get();

echo "Found " . $txs->count() . " suspect transactions since $startDate\n\n";

foreach ($txs as $tx) {
    $user = $tx->user;
    echo "ID: {$tx->id}\n";
    echo "User: {$user->id} ({$user->name})\n";
    echo "Amount: {$tx->amount}\n";
    echo "Source: {$tx->source}\n";
    echo "Description: " . ($tx->meta['description'] ?? 'N/A') . "\n";
    echo "Date: {$tx->created_at}\n";
    echo "-------------------\n";
}
