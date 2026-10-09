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

// 1. Setup - Create a distant member
$user = User::factory()->create([
    'is_distant' => true,
    'balance' => 0,
    'outstanding_fines' => 1000,
    'admin_charge_balance' => 500,
]);

echo "Created user {$user->id} (is_distant: {$user->is_distant})\n";
echo "Initial Balance: " . number_format($user->balance, 2) . "\n";
echo "Outstanding Fines: {$user->outstanding_fines}\n";
echo "Admin Charge Balance: {$user->admin_charge_balance}\n";

// 2. Disable Meeting Fee Auto-Deduction
Setting::set('auto_meeting_fine_deduction_enabled', 0); // Filament might store as string or int
Setting::set('auto_fine_deduction_enabled', 1);

echo "Settings set: auto_meeting_fine_deduction_enabled = 0, auto_fine_deduction_enabled = 1\n";

// 3. Perform a manual credit (simulating refund or manual credit)
echo "Performing manual credit of 2000...\n";
$service = app(AdministrativeChargeService::class);
$result = $service->applyManualTransaction($user, 2000, 'credit', 'Repro test');

$user->refresh();
echo "Resulting Balance: " . number_format($user->balance, 2) . "\n";
echo "Resulting Outstanding Fines: {$user->outstanding_fines}\n";
echo "Resulting Admin Charge Balance: {$user->admin_charge_balance}\n";
echo "Admin Charge Deducted in Result: {$result['admin_charge_deducted']}\n";

// Check maintenance charge
$maintenanceCharge = $result['maintenance_charge'];
$expectedBalance = 2000 - $maintenanceCharge;

if ($user->balance < $expectedBalance) {
    echo "FAILED: Automatic deduction occurred despite being disabled!\n";
} else {
    echo "SUCCESS: No automatic deduction occurred.\n";
}

// Cleanup
$user->delete();
