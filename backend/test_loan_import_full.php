<?php

use App\Imports\LoansImport;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Ensure a test user exists
$user = User::firstOrCreate(
    ['membership_number' => 'TEST001'],
    [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'branch_id' => 1
    ]
);

echo "Test User ID: " . $user->id . "\n";

// Create a temporary CSV file
$csvPath = __DIR__ . '/test_loans.csv';
$csvContent = "membership_no,original_loan_amount,total_repaid_to_date,remaining_principal,next_installment_amount,interval,total_installments,received_at,defaulted_at\n";
$csvContent .= "TEST001,100000,20000,80000,10000,monthly,10,2023-01-01,\n";
file_put_contents($csvPath, $csvContent);

echo "CSV created at: $csvPath\n";

try {
    echo "Starting import...\n";
    Excel::import(new LoansImport(now()), $csvPath);
    echo "Import finished.\n";
} catch (\Throwable $e) {
    echo "Import failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

// Check if loan was created
$loan = \App\Models\QardHasan::where('user_id', $user->id)->first();
if ($loan) {
    echo "Success! Loan found: " . $loan->qard_id_string . " with principal: " . $loan->principal_amount . "\n";
} else {
    echo "Failure: No loan found for test user.\n";
}

// Cleanup
if (file_exists($csvPath)) {
    unlink($csvPath);
}
