<?php

use App\Imports\LoansImport;
use App\Models\User;
use App\Models\QardHasan;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Override DB settings for local run outside Docker
config(['database.connections.mysql.host' => 'localhost']);
config(['database.connections.mysql.port' => '33060']);
config(['database.connections.mysql.database' => 'coop_attaqwa']);
config(['database.connections.mysql.username' => 'sail_attaqwa']);
config(['database.connections.mysql.password' => 'pass_attaqwa']);

// Create a test user
$membershipNo = 'TEST_DUP_001';
$user = User::updateOrCreate(
    ['membership_number' => $membershipNo],
    [
        'name' => 'Test Duplicate User',
        'email' => 'test_dup@example.com',
        'password' => bcrypt('password'),
    ]
);

echo "User ID: " . $user->id . "\n";

// Clean up existing loans for this user to start fresh
QardHasan::where('user_id', $user->id)->delete();

$import = new LoansImport(now());

$row = [
    'membership_no' => $membershipNo,
    'original_loan_amount' => '100000',
    'remaining_principal' => '40000',
    'next_installment_amount' => '10000',
    'total_repaid_to_date' => '60000',
    'interval' => 'monthly',
    'total_installments' => '10',
    'received_at' => '2026-01-01',
    'defaulted_at' => '',
];

echo "First import...\n";
$model1 = $import->model($row);
if ($model1) {
    $model1->save();
    echo "Loan 1 saved. ID: " . $model1->id . "\n";
}

echo "Second import (same data)...\n";
$model2 = $import->model($row);
if ($model2) {
    $model2->save();
    echo "Loan 2 saved. ID: " . $model2->id . "\n";
} else {
    echo "Loan 2 was NOT created (expected behavior after fix).\n";
}

$count = QardHasan::where('user_id', $user->id)->count();
echo "Total loans for user: $count\n";

if ($count > 1) {
    echo "FAIL: Duplicate loans found!\n";
} else {
    echo "PASS: No duplicate loans.\n";
}
