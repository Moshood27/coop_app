<?php
use App\Models\User;
use App\Http\Controllers\Api\AdminMemberController;
use Illuminate\Http\Request;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = User::where('is_admin', true)->first();
$member = User::find(1835);

if (!$member) {
    echo "Member 1835 not found, picking another one.\n";
    $member = User::where('is_admin', false)->first();
}

if (!$admin || !$member) {
    die("Admin or member not found.\n");
}

// Ensure member has an active loan to trigger the exception
$member->loans()->updateOrCreate(['status' => 'active'], [
    'amount' => 1000,
    'total_installments' => 10,
    'interval' => 'monthly',
    'repayment_start_date' => now(),
    'reference' => 'REPRO-TEST-' . time()
]);

echo "Member #{$member->id} active loan count: " . $member->loans()->whereIn('status', ['active', 'defaulted'])->count() . "\n";

$request = Request::create("/api/admin/members/{$member->id}/loans", 'POST', [
    'amount' => 3000000,
    'total_installments' => 167000,
    'interval' => 'monthly',
    'description' => 'Business',
    'repayment_start_date' => '2026-05-04'
]);

$request->setUserResolver(fn() => $admin);

try {
    $controller = app(AdminMemberController::class);
    $response = $controller->createLoan($request, $member->id);
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Content: " . $response->getContent() . "\n";
} catch (\Throwable $e) {
    echo "Caught Exception: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
}
