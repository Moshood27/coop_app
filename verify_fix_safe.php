<?php

use App\Models\User;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\AdministrativeChargeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Boot Laravel
require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::beginTransaction();

try {
    // 1. Setup a distant user with outstanding fines
    $user = User::factory()->create([
        'is_distant' => true,
        'balance' => 0,
        'outstanding_fines' => 1000,
    ]);

    echo "Created user {$user->id} (Distant) with 1000 outstanding fines.\n";

    // 2. Disable Meeting Fee Auto-Deduction
    Setting::set('auto_meeting_fine_deduction_enabled', false);
    Setting::set('auto_fine_deduction_enabled', true); 
    
    echo "Disabled auto_meeting_fine_deduction_enabled.\n";

    // 3. Simulate wallet funding
    $ref = 'TEST_REF_' . Str::random(8);
    $amount = 5000;
    
    echo "Simulating funding of ₦{$amount} with ref {$ref}...\n";
    
    $user->increment('balance', $amount);
    
    WalletTransaction::create([
        'user_id' => $user->id,
        'type' => 'credit',
        'amount' => $amount,
        'reference' => $ref,
        'source' => 'paystack_dva',
    ]);

    $chargeService = app(AdministrativeChargeService::class);
    $result = $chargeService->applyDeductionsFromWallet($user, $amount, true, $ref);

    $user->refresh();

    echo "Resulting Outstanding Fines: {$user->outstanding_fines}\n";
    echo "Resulting Balance: {$user->balance}\n";

    if ((float)$user->outstanding_fines === 1000.0) {
        echo "SUCCESS: Fine was NOT deducted.\n";
    } else {
        echo "FAILURE: Fine was deducted! Value: {$user->outstanding_fines}\n";
    }

    // Check for FINE transactions
    $fineTxs = WalletTransaction::where('user_id', $user->id)
        ->where(function($q) {
            $q->where('source', 'attendance_fine_collection')
              ->orWhere('reference', 'like', 'FINE_COLLECT%');
        })
        ->get();
        
    if ($fineTxs->count() === 0) {
        echo "SUCCESS: No fine collection transactions found.\n";
    } else {
        echo "FAILURE: Found {$fineTxs->count()} fine collection transactions.\n";
        foreach ($fineTxs as $tx) {
            echo " - Tx: {$tx->reference}, Amount: {$tx->amount}\n";
        }
    }

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    DB::rollBack();
    echo "Database rolled back.\n";
}
