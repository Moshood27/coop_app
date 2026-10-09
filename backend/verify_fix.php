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

function verifyFix() {
    echo "Verifying Fix...\n";

    // Setup distant user
    $user = User::factory()->create([
        'is_distant' => true,
        'balance' => 0,
        'outstanding_fines' => 1000,
        'admin_charge_balance' => 500,
    ]);

    // Scenario 1: Specific toggle OFF, Global toggle ON
    Setting::set('auto_meeting_fine_deduction_enabled', '0');
    Setting::set('auto_fine_deduction_enabled', '1');

    $service = app(AdministrativeChargeService::class);
    echo "Scenario 1: Meeting Toggle OFF, Global Toggle ON\n";
    echo "  isAutoDeductionEnabled: " . ($service->isAutoDeductionEnabled($user) ? 'YES' : 'NO') . "\n";

    if ($service->isAutoDeductionEnabled($user)) {
        echo "  FAILED: Should be disabled when specific toggle is OFF\n";
    } else {
        echo "  PASSED\n";
    }

    // Scenario 2: Specific toggle ON, Global toggle OFF
    Setting::set('auto_meeting_fine_deduction_enabled', '1');
    Setting::set('auto_fine_deduction_enabled', '0');

    echo "Scenario 2: Meeting Toggle ON, Global Toggle OFF\n";
    echo "  isAutoDeductionEnabled: " . ($service->isAutoDeductionEnabled($user) ? 'YES' : 'NO') . "\n";

    if ($service->isAutoDeductionEnabled($user)) {
        echo "  FAILED: Should be disabled when Global Toggle (App Status) is OFF\n";
    } else {
        echo "  PASSED\n";
    }

    // Cleanup
    $user->delete();
}

verifyFix();
