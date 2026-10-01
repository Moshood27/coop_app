<?php

use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Contribution;
use App\Models\Scheme;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Fixing Double Debited Users ---\n";

DB::transaction(function() {
    // User 1402: AbdulAzeez
    $user1402 = User::find(1402);
    if ($user1402) {
        echo "Fixing User 1402 (AbdulAzeez)...\n";
        $refundAmount = 300.00;
        $user1402->increment('balance', $refundAmount);
        
        WalletTransaction::create([
            'user_id' => $user1402->id,
            'type' => 'credit',
            'amount' => $refundAmount,
            'reference' => 'REFUND-ADMIN-CHG-1402-' . time(),
            'source' => 'admin_charge_refund',
            'meta' => ['description' => 'Refund for double debit on 2026-10-01']
        ]);
        echo "  Refunded 300.00. New Balance: {$user1402->balance}\n";
    }

    // User 1412: Rasaq
    $user1412 = User::find(1412);
    if ($user1412) {
        echo "Fixing User 1412 (Rasaq)...\n";
        $refundAmount = 200.00;
        $user1412->increment('balance', $refundAmount);
        $user1412->admin_charge_balance = 0;
        $user1412->save();
        
        WalletTransaction::create([
            'user_id' => $user1412->id,
            'type' => 'credit',
            'amount' => $refundAmount,
            'reference' => 'REFUND-ADMIN-CHG-1412-' . time(),
            'source' => 'admin_charge_refund',
            'meta' => ['description' => 'Refund for double debit on 2026-10-01']
        ]);
        echo "  Refunded 200.00. New Balance: {$user1412->balance}. Admin Charge Balance reset to 0.\n";
    }
});

echo "\n--- Fix Completed ---\n";
