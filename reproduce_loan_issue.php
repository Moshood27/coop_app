<?php

use App\Models\User;
use App\Models\QardHasan;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// User 1835 as per issue
$user = User::find(1835);
if (!$user) {
    echo "User 1835 not found.\n";
    exit(1);
}

// Admin 1784 as per issue
$admin = User::find(1784);
if (!$admin) {
    echo "Admin 1784 not found.\n";
    exit(1);
}

echo "Testing createLoan for User: {$user->full_name} (ID: {$user->id})\n";
echo "Has active loan: " . ($user->hasActiveLoan() ? 'Yes' : 'No') . "\n";

$request = Request::create("/api/admin/members/{$user->id}/loans", 'POST', [
    'amount' => 3000000,
    'total_installments' => 167000,
    'interval' => 'monthly',
    'description' => 'Business',
    'repayment_start_date' => '2026-05-04',
]);

// Set authenticated user for request
$request->setUserResolver(fn() => $admin);

try {
    $controller = app(\App\Http\Controllers\Api\AdminMemberController::class);
    $response = $controller->createLoan($request, $user);
    
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content: " . $response->getContent() . "\n";
} catch (\Throwable $e) {
    echo "Caught unexpected exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
