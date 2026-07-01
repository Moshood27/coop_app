<?php

use App\Imports\LoansImport;
use App\Models\User;
use App\Models\QardHasan;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Override DB settings for local run outside Docker
config(['database.connections.mysql.host' => '127.0.0.1']);
config(['database.connections.mysql.port' => '33060']);
config(['database.connections.mysql.database' => 'coop_attaqwa']);
config(['database.connections.mysql.username' => 'sail_attaqwa']);
config(['database.connections.mysql.password' => 'pass_attaqwa']);

// Create a test user if it doesn't exist
$user = User::firstOrCreate(
    ['membership_number' => 'MIG-TEST-001'],
    [
        'name' => 'Migration Test User',
        'phone' => '08000000000',
        'password' => bcrypt('password'),
        'branch_id' => 1, // Assume branch 1 exists
    ]
);

echo "User ID: " . $user->id . "\n";

$row = [
    'membership_no' => 'MIG-TEST-001',
    'original_loan_amount' => '120000',
    'total_repaid_to_date' => '20000',
    'remaining_principal' => '100000',
    'next_installment_amount' => '10000',
    'interval' => 'monthly',
    'total_installments' => '12',
    'received_at' => '2024-01-01',
    'defaulted_at' => '',
];

$import = new LoansImport(now());

try {
    echo "Running model()...\n";
    $model = $import->model($row);
    if ($model) {
        echo "per_installment: " . $model->per_installment . "\n";
        echo "Saving model...\n";
        $model->save();
        echo "Success! Loan ID: " . $model->id . "\n";
    } else {
        echo "Model is null\n";
    }
} catch (\Throwable $e) {
    echo "Caught: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "At: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
