<?php

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

use App\Models\User;
use App\Models\Scheme;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\AdminMemberController;
use Illuminate\Http\Request;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Setup test data
$admin = User::whereHas('roles', function($q) { $q->where('name', 'super_admin'); })->first();
if (!$admin) {
    echo "No admin found, creating one...\n";
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
}

$user = User::factory()->create(['balance' => 1000]);
$scheme = Scheme::first() ?: Scheme::create(['name' => 'Savings', 'active' => true]);

echo "Testing allocateWallet for user {$user->id} with amount 500...\n";

$request = Request::create('/api/admin/members/' . $user->id . '/allocate-wallet', 'POST', [
    'allocations' => [
        [
            'scheme_id' => $scheme->id,
            'amount' => 500,
        ]
    ]
]);
$request->setUserResolver(fn() => $admin);

$controller = app(AdminMemberController::class);

try {
    $response = $controller->allocateWallet($request, $user);
    echo "Success! Response: " . $response->getContent() . "\n";
} catch (\Exception $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
    if (str_contains($e->getMessage(), "Field 'reference' doesn't have a default value")) {
        echo "VERIFIED: The issue is reproduced.\n";
    }
} finally {
    // Cleanup if needed, but since it's a test db/env it might be okay.
    // Actually we might want to delete the user to avoid clutter.
    $user->delete();
}
