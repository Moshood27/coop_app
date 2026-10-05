<?php
use App\Models\User;
use App\Models\Setting;
use App\Services\AdministrativeChargeService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::beginTransaction();

try {
    // 1. Setup User
    $user = User::factory()->create([
        'balance' => 0,
        'outstanding_fines' => 500,
        'admin_charge_balance' => 1000,
        'is_distant' => true,
    ]);

    echo "User ID: {$user->id}, Is Distant: " . ($user->is_distant ? 'Yes' : 'No') . "\n";
    echo "Initial Outstanding Fines: {$user->outstanding_fines}\n";
    echo "Initial Admin Charge Balance: {$user->admin_charge_balance}\n";

    // 2. Disable all relevant settings
    Setting::set('auto_fine_deduction_enabled', false);
    Setting::set('auto_meeting_fine_deduction_enabled', false);
    Setting::set('auto_sitting_fine_deduction_enabled', false);
    Setting::set('auto_admin_charge_deduction_enabled', false);

    echo "Settings after disabling:\n";
    echo "auto_fine_deduction_enabled: " . var_export(Setting::get('auto_fine_deduction_enabled'), true) . " (bool): " . var_export((bool)Setting::get('auto_fine_deduction_enabled'), true) . "\n";
    echo "auto_meeting_fine_deduction_enabled: " . var_export(Setting::get('auto_meeting_fine_deduction_enabled'), true) . " (bool): " . var_export((bool)Setting::get('auto_meeting_fine_deduction_enabled'), true) . "\n";

    // 3. Fund wallet
    $amount = 5000;
    $user->increment('balance', $amount);
    echo "Wallet funded with {$amount}. New balance: {$user->balance}\n";

    // 4. Run deductions
    $service = app(AdministrativeChargeService::class);
    $result = $service->applyDeductionsFromWallet($user, $amount, true, 'repro_ref');

    echo "Deduction Results:\n";
    print_r($result['deductions']);

    $user->refresh();
    echo "Final Balance: {$user->balance}\n";
    echo "Final Outstanding Fines: {$user->outstanding_fines}\n";
    echo "Final Admin Charge Balance: {$user->admin_charge_balance}\n";

    if (!empty($result['deductions'])) {
        echo "ISSUE REPRODUCED: Deductions were applied despite being disabled!\n";
    } else {
        echo "Issue not reproduced with these settings.\n";
    }

} finally {
    DB::rollBack();
}
