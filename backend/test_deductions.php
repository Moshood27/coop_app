<?php

use App\Models\User;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\AdministrativeChargeService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function testDeduction($isDistant, $settingKey, $settingValue, $globalFineValue) {
    echo "Testing: is_distant=" . ($isDistant ? 'YES' : 'NO') . ", $settingKey=" . ($settingValue ? 'ON' : 'OFF') . ", auto_fine_deduction_enabled=" . ($globalFineValue ? 'ON' : 'OFF') . "\n";

    // Setup
    $user = User::factory()->create([
        'is_distant' => $isDistant,
        'balance' => 0,
        'outstanding_fines' => 1000,
        'admin_charge_balance' => 500,
    ]);

    Setting::set($settingKey, $settingValue ? '1' : '0');
    Setting::set('auto_fine_deduction_enabled', $globalFineValue ? '1' : '0');

    $service = app(AdministrativeChargeService::class);

    echo "  Initial Balance: {$user->balance}\n";
    echo "  Initial Admin Charge: {$user->admin_charge_balance}\n";
    echo "  Initial Fines: {$user->outstanding_fines}\n";

    echo "  Performing Manual Credit of 2000...\n";
    $service->applyManualTransaction($user, 2000, 'credit', 'Test');

    $user->refresh();
    echo "  Final Balance: {$user->balance}\n";
    echo "  Final Admin Charge: {$user->admin_charge_balance}\n";
    echo "  Final Fines: {$user->outstanding_fines}\n";

    $maintenance = $service->calculateMaintenanceCharge(2000);
    $expected = 2000 - $maintenance;

    if ($user->balance < $expected) {
        echo "  RESULT: DEDUCTION OCCURRED (" . ($expected - $user->balance) . ")\n";
    } else {
        echo "  RESULT: NO DEDUCTION\n";
    }

    $user->delete();
    echo "-----------------------------------\n";
}

echo "Starting tests...\n";

// Case 1: Distant member, Meeting Fee deduction disabled, Global Fine deduction enabled
testDeduction(true, 'auto_meeting_fine_deduction_enabled', false, true);

// Case 2: Distant member, Meeting Fee deduction enabled, Global Fine deduction disabled
testDeduction(true, 'auto_meeting_fine_deduction_enabled', true, false);

// Case 3: Regular member, Sitting Fee deduction disabled, Global Fine deduction enabled
testDeduction(false, 'auto_sitting_fine_deduction_enabled', false, true);

// Case 4: Refund test
echo "Testing Refund scenario...\n";
$user = User::factory()->create(['balance' => 0, 'admin_charge_balance' => 500, 'is_distant' => true]);
Setting::set('auto_meeting_fine_deduction_enabled', '1');
$tx = WalletTransaction::create([
    'user_id' => $user->id,
    'type' => 'debit',
    'amount' => 500,
    'source' => 'manual',
    'reference' => 'TEST-' . time()
]);
echo "  Initial Balance: {$user->balance}\n";
echo "  Refunding 500...\n";
app(AdministrativeChargeService::class)->refundTransaction($tx);
$user->refresh();
echo "  Final Balance: {$user->balance}\n";
if ($user->balance < 500) {
    echo "  RESULT: DEDUCTION OCCURRED DURING REFUND!\n";
} else {
    echo "  RESULT: NO DEDUCTION DURING REFUND\n";
}
$user->delete();
