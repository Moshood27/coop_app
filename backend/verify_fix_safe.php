<?php

namespace {
    use App\Models\User;
    use App\Models\Setting;
    use App\Models\WalletTransaction;
    use App\Services\AdministrativeChargeService;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Str;

    // Boot Laravel
    require __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    DB::beginTransaction();

    try {
        // 1. Setup a distant user with outstanding fines
        // We use a random ID or just let factory handle it, but we MUST rollback.
        $user = User::factory()->create([
            'is_distant' => true,
            'balance' => 0,
            'outstanding_fines' => 1000,
            'email' => 'test_verify_' . Str::random(8) . '@example.com',
        ]);

        echo "Created user {$user->id} (Distant) with 1000 outstanding fines.\n";

        // 2. Disable Meeting Fee Auto-Deduction
        // Save current setting to restore just in case, though we are in transaction
        $originalSetting = Setting::get('auto_meeting_fine_deduction_enabled', true);
        Setting::set('auto_meeting_fine_deduction_enabled', false);
        Setting::set('auto_fine_deduction_enabled', true);

        echo "Disabled auto_meeting_fine_deduction_enabled (was " . ($originalSetting ? 'true' : 'false') . ").\n";

        // 3. Simulate wallet funding
        $ref = 'TEST_REF_' . Str::random(8);
        $amount = 5000;

        echo "Simulating funding of ₦{$amount} with ref {$ref}...\n";

        // We need to bypass the observer for the initial credit if we want to test applyDeductionsFromWallet separately,
        // OR we just let the observer trigger and check if IT deduced.
        // The issue description mentions "funding their wallet through paystack", which calls applyDeductionsFromWallet in WebhookController.

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

        $success = true;
        if ((float)$user->outstanding_fines === 1000.0) {
            echo "SUCCESS: Fine was NOT auto-deducted from inflow.\n";
        } else {
            echo "FAILURE: Fine was auto-deducted from inflow! Value: {$user->outstanding_fines}\n";
            $success = false;
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
            $success = false;
        }

        if ($success) {
            echo "\nOverall result: VERIFIED\n";
        } else {
            echo "\nOverall result: FAILED\n";
        }

    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
    } finally {
        DB::rollBack();
        echo "Database rolled back. All changes reverted.\n";
    }
}
