<?php
require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
putenv('CACHE_DRIVER=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=sync');
putenv('TELESCOPE_ENABLED=false');
$kernel->bootstrap();

use App\Models\User;
use App\Models\Scheme;
use App\Models\Contribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$user = User::where('balance', '>', 2000)->first();
if (!$user) {
    $user = User::factory()->create(['balance' => 5000, 'name' => 'Test User']);
}

echo "Testing combined allocation for User ID: {$user->id}\n";

$shares = Scheme::where('name', 'Shares')->first();
$savings = Scheme::where('name', 'Savings')->first();

if (!$shares || !$savings) {
    echo "Shares or Savings scheme not found.\n";
    exit;
}

$totalAmount = 1000;
$half = $totalAmount / 2;

$reference = 'WALLET_ALLOC_' . now()->format('YmdHis') . '_' . $user->id . '_' . bin2hex(random_bytes(3));

DB::transaction(function () use ($user, $shares, $savings, $totalAmount, $half, $reference) {
    $user->decrement('balance', $totalAmount);

    // Simulate WalletController logic
    foreach ([$shares, $savings] as $scheme) {
        Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $scheme->id,
            'amount' => $half,
            'reference' => $reference,
            'status' => 'success',
            'category' => 'deposit',
            'paid_at' => now(),
        ]);
    }
});

echo "Contributions created. Checking passbook visibility...\n";

$year = (int)date('Y');
$passbookController = new \App\Http\Controllers\Api\PassbookController();
$request = new \Illuminate\Http\Request();
$request->setUserResolver(fn() => $user);

$response = $passbookController->getMatrix($request, $year);
$data = json_decode($response->getContent(), true);

$matrix = $data['matrix'];
$foundShares = false;
$foundSavings = false;

foreach ($matrix as $row) {
    if ($row['scheme_name'] === 'Shares') {
        echo "Shares Row Total: {$row['total']}\n";
        if ($row['total'] >= $half) $foundShares = true;
    }
    if ($row['scheme_name'] === 'Savings') {
        echo "Savings Row Total: {$row['total']}\n";
        if ($row['total'] >= $half) $foundSavings = true;
    }
}

if ($foundShares && $foundSavings) {
    echo "SUCCESS: Both contributions found in passbook matrix.\n";
} else {
    echo "FAILURE: One or both contributions NOT found in passbook matrix.\n";
    echo "Checking database records directly...\n";
    $records = Contribution::where('user_id', $user->id)->where('reference', $reference)->get();
    foreach ($records as $r) {
        echo "ID: {$r->id}, Scheme ID: {$r->scheme_id}, Amount: {$r->amount}, Status: {$r->status}, paid_at: {$r->paid_at}\n";
    }
}
