<?php

use App\Models\User;
use App\Models\Scheme;
use App\Models\Contribution;
use App\Services\FinancialReconciliationService;
use App\Services\LedgerService;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Setup mock/test data
echo "Setting up test data...\n";
$user = User::where('email', 'tester@example.com')->first();
if (!$user) {
    $user = User::create([
        'surname' => 'Test',
        'name' => 'User',
        'email' => 'tester@example.com',
        'password' => bcrypt('password'),
        'membership_number' => 'TEST-001',
        'branch_id' => 1,
    ]);
}

$savingsScheme = Scheme::firstOrCreate(['name' => 'Savings'], ['active' => true]);
$ordinarySavingsScheme = Scheme::firstOrCreate(['name' => 'Ordinary Savings'], ['active' => true]);

// Set user balance to 0
$user->ordinary_savings = 0;
$user->save();

// Create contributions for both
Contribution::create([
    'user_id' => $user->id,
    'scheme_id' => $savingsScheme->id,
    'amount' => 5000,
    'status' => 'success',
    'reference' => 'TEST-SAV-1',
    'category' => 'savings'
]);

Contribution::create([
    'user_id' => $user->id,
    'scheme_id' => $ordinarySavingsScheme->id,
    'amount' => 3000,
    'status' => 'success',
    'reference' => 'TEST-ORD-1',
    'category' => 'savings'
]);

echo "Initial user ordinary_savings: {$user->ordinary_savings}\n";

// 2. Run reconciliation with fix
$service = new FinancialReconciliationService(app(LedgerService::class));
echo "Running reconciliation with fix for user ID {$user->id}...\n";
$service->run(true, $user->id);

$user->refresh();
echo "Calculated total should be 5000 + 3000 = 8000\n";
echo "After reconciliation, user ordinary_savings: {$user->ordinary_savings}\n";

if (abs($user->ordinary_savings - 8000) < 0.01) {
    echo "SUCCESS: Balance correctly aggregated and not wiped!\n";
} else {
    echo "FAILURE: Balance is {$user->ordinary_savings}, expected 8000.\n";
}

// Cleanup (optional but good practice)
// $user->contributions()->delete();
// $user->delete();
