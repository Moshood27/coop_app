<?php

use App\Models\User;
use App\Models\QardHasan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Override DB connection for local execution if needed
config(['database.connections.mysql.host' => '127.0.0.1']);
config(['database.connections.mysql.port' => '33060']);
config(['database.connections.mysql.username' => 'sail_attaqwa']);
config(['database.connections.mysql.password' => 'pass_attaqwa']);

// Find an admin and a loan
$admin = User::where('is_admin', true)->first();
if (!$admin) {
    echo "No admin user found.\n";
    exit(1);
}

// Act as admin
Auth::login($admin);

$loan = QardHasan::first();
if (!$loan) {
    // Create a dummy loan if none exists
    $user = User::where('is_admin', false)->first() ?: User::first();
    $loan = QardHasan::create([
        'user_id' => $user->id,
        'qard_id_string' => 'QH-' . strtoupper(Str::random(6)),
        'principal_amount' => 50000,
        'total_installments' => 10,
        'per_installment' => 5000,
        'interval' => 'monthly',
        'paid_amount' => 0,
        'status' => 'active',
        'disbursed_at' => now(),
    ]);
}

echo "Testing loan repayment for Loan ID: {$loan->id}\n";

try {
    $request = Request::create('/api/admin/members/loans/' . $loan->id . '/repay', 'POST', [
        'amount' => 2000,
        'method' => 'cash',
        'paid_at' => '2026-08-05',
        'note' => 'Test repayment',
    ]);
    
    // We need to bypass validation or mock it if we call the controller method directly with a manual Request object
    // But AdminMemberController@loanRepayment calls $request->validate()
    
    $controller = app(\App\Http\Controllers\Api\AdminMemberController::class);
    $response = $controller->loanRepayment($request, $loan);
    
    echo "Success!\n";
    print_r($response->getData());
} catch (\Illuminate\Database\QueryException $e) {
    echo "Caught expected QueryException: " . $e->getMessage() . "\n";
} catch (\Throwable $e) {
    echo "Caught unexpected exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
    // echo $e->getTraceAsString() . "\n";
}
