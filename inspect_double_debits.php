<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userIds = [1402, 1412];
foreach ($userIds as $id) {
    $user = \App\Models\User::find($id);
    $txs = \App\Models\WalletTransaction::where('user_id', $id)
        ->where('source', 'admin_charge')
        ->whereDate('created_at', '2026-10-01')
        ->get();
    
    echo "User $id ({$user->name}):\n";
    echo "  Is Distant: " . ($user->is_distant ? 'Yes' : 'No') . "\n";
    echo "  Wallet Balance: {$user->balance}\n";
    echo "  Admin Charge Balance: {$user->admin_charge_balance}\n";
    
    foreach ($txs as $tx) {
        echo "  - TX ID: {$tx->id}, Amount: {$tx->amount}, Ref: {$tx->reference}, Created: {$tx->created_at}\n";
        $contribution = \App\Models\Contribution::where('reference', $tx->reference)->first();
        if ($contribution) {
            echo "    - Contribution ID: {$contribution->id}, Amount: {$contribution->amount}, Notes: {$contribution->notes}\n";
        }
    }
    echo "\n";
}
