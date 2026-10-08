<?php

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

function simulateInflow($userId, $amount, $ref) {
    $user = User::find($userId);
    echo "Processing inflow for user {$user->id}, amount {$amount}, ref {$ref}\n";

    DB::transaction(function () use ($user, $amount, $ref) {
        // Simulate WebhookController logic: increment balance first
        $user->increment('balance', $amount);

        WalletTransaction::create([
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => $amount,
            'reference' => $ref,
            'source' => 'paystack_dva',
        ]);

        $chargeService = app(AdministrativeChargeService::class);

        // --- SIMULATED RACE CONDITION START ---
        // We load the user data here as it would be in a concurrent process
        // that hasn't seen the increment/decrement from the other process yet.
        // But since we are in the same script, we'll just demonstrate that
        // without a lock, we are using potentially stale data if we were concurrent.

        // In reality, Process B would have loaded $user BEFORE Process A committed.
        // Since we can't easily fork, we'll just show that applyDeductionsFromWallet
        // doesn't lock and uses the $user object passed to it.

        $chargeService->applyDeductionsFromWallet($user, $amount, true, $ref);
        // --- SIMULATED RACE CONDITION END ---
    });
}

DB::beginTransaction();

try {
    $user = User::factory()->create([
        'balance' => 0,
        'outstanding_fines' => 1000,
        'is_distant' => true,
    ]);
    Setting::set('auto_fine_deduction_enabled', true);
    Setting::set('auto_meeting_fine_deduction_enabled', true);

    echo "Initial Outstanding Fines: {$user->outstanding_fines}\n";

    // Simulate two inflows that happen nearly at the same time
    // Process A
    $userA = User::find($user->id);

    // Process B loads the user at the same time
    $userB = User::find($user->id);

    echo "Process A starts...\n";
    simulateInflow($user->id, 5000, 'REF_A');

    echo "Process B starts with stale user object (outstanding_fines still 1000)...\n";
    // If simulateInflow used the $user object passed to it, and that object was stale...
    // But simulateInflow re-fetches with User::find($userId).
    // However, AdministrativeChargeService::applyDeductionsFromWallet uses the passed $user.

    // Let's call the service directly with stale objects to prove the point.
    $chargeService = app(AdministrativeChargeService::class);

    DB::transaction(function() use ($chargeService, $userA) {
        $chargeService->applyDeductionsFromWallet($userA, 5000, true, 'REF_A');
    });

    $user->refresh();
    echo "After Process A, Outstanding Fines: {$user->outstanding_fines}\n";

    DB::transaction(function() use ($chargeService, $userB) {
        // $userB still has outstanding_fines = 1000 in its attributes!
        $chargeService->applyDeductionsFromWallet($userB, 5000, true, 'REF_B');
    });

    $user->refresh();
    echo "After Process B (using stale object), Outstanding Fines: {$user->outstanding_fines}\n";

    if ($user->outstanding_fines < 0) {
        echo "RACE CONDITION REPRODUCED: Fines are negative!\n";
    }

} finally {
    DB::rollBack();
}
