<?php

use App\Models\User;
use App\Models\Scheme;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AdminMemberController;
use App\Services\PassbookService;
use App\Services\AttendanceService;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Setup Data
$admin = User::where('is_admin', true)->first();
if (!$admin) {
    echo "No admin user found. Creating one...\n";
    $admin = User::factory()->create(['is_admin' => true, 'balance' => 10000]);
} else {
    $admin->update(['balance' => 10000]);
}

$member = User::where('is_admin', false)->first();
if (!$member) {
    echo "No member user found. Creating one...\n";
    $member = User::factory()->create(['is_admin' => false]);
}

$scheme = Scheme::first();
if (!$scheme) {
    echo "No scheme found. Creating one...\n";
    $scheme = Scheme::create(['name' => 'General Savings', 'category' => 'deposit']);
}

echo "Admin ID: {$admin->id}, Member ID: {$member->id}, Scheme ID: {$scheme->id}\n";

// 2. Mock Request
$request = new Request();
$request->setUserResolver(fn() => $admin);
$request->merge([
    'allocations' => [
        ['scheme_id' => $scheme->id, 'amount' => 100]
    ],
    'notes' => 'Test Allocation'
]);

// 3. Call Controller Method
$passbookService = $app->make(PassbookService::class);
$attendanceService = $app->make(AttendanceService::class);
$controller = new AdminMemberController($passbookService, $attendanceService);

try {
    echo "Attempting to allocate funds...\n";
    $response = $controller->allocateFromAdminWallet($request, $member);
    
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response body: " . $response->getContent() . "\n";

    if ($response->getStatusCode() === 200) {
        echo "SUCCESS: Funds allocated without integrity violation.\n";
        
        // Verify transactions
        $adminTx = WalletTransaction::where('user_id', $admin->id)->orderBy('id', 'desc')->first();
        $memberTx = WalletTransaction::where('user_id', $member->id)->orderBy('id', 'desc')->first();
        
        echo "Admin Tx Ref: " . ($adminTx->reference ?? 'NOT FOUND') . "\n";
        echo "Member Tx Ref: " . ($memberTx->reference ?? 'NOT FOUND') . "\n";
        
        if ($adminTx && $memberTx && $adminTx->reference !== $memberTx->reference) {
            echo "VERIFIED: References are unique.\n";
        } else {
            echo "FAILED: References are not unique or transactions not found.\n";
        }
    } else {
        echo "FAILED: Received non-200 response.\n";
    }
} catch (\Exception $e) {
    echo "ERROR: Caught exception: " . $e->getMessage() . "\n";
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        echo "CONFIRMED: Still seeing integrity violation!\n";
    }
}
