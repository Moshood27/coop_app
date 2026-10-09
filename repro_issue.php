<?php

use App\Models\User;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Services\AdministrativeChargeService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Setup - Create a distant member
$user = User::factory()->create([
    'is_distant' => true,
    'balance' => 0,
    'outstanding_fines' => 1000,
    'admin_charge_balance' => 500,
]);

echo "Created user {$user->id} (is_distant: {$user->is_distant})\n";
echo "Initial Balance: {$user->balance}\n";
echo "Outstanding Fines: {$user->outstanding_fines}\n";
echo "Admin Charge Balance: {$user->admin_charge_balance}\n";

// 2. Disable Meeting Fee Auto-Deduction
Setting::set('auto_meeting_fine_deduction_enabled', false);
Setting::set('auto_fine_deduction_enabled', true); // Keep this enabled to see if it respects the distant-specific one

echo "Settings: auto_meeting_fine_deduction_enabled = false, auto_fine_deduction_enabled = true\n";

// 3. Perform a manual credit (simulating refund or manual credit)
echo "Performing manual credit of 2000...\n";
$service = app(AdministrativeChargeService::class);
$result = $service->applyManualTransaction($user, 2000, 'credit', 'Repro test');

$user->refresh();
echo "Resulting Balance: {$user->balance}\n";
echo "Resulting Outstanding Fines: {$user->outstanding_fines}\n";
echo "Resulting Admin Charge Balance: {$user->admin_charge_balance}\n";
echo "Admin Charge Deducted in Result: {$result['admin_charge_deducted']}\n";

if ($user->balance < 2000 - $result['maintenance_charge']) {
    echo "FAILED: Automatic deduction occurred despite being disabled!\n";
} else {
    echo "SUCCESS: No automatic deduction occurred.\n";
}

// Cleanup
$user->delete();
