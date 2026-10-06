<?php

use App\Models\User;
use App\Models\QardHasan;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userId = 1784;
$user = User::find($userId);

if (!$user) {
    echo "User $userId not found.\n";
    exit;
}

echo "Checking loans for User ID: {$user->id} ({$user->full_name})\n";

$loans = QardHasan::where('user_id', $user->id)->get();

if ($loans->isEmpty()) {
    echo "No loans found for this user.\n";
} else {
    foreach ($loans as $loan) {
        echo "Loan ID: {$loan->id}, Status: {$loan->status}, Principal: {$loan->principal_amount}, Paid: {$loan->paid_amount}, Remaining: " . ($loan->principal_amount - $loan->paid_amount) . "\n";
    }
}

echo "hasActiveLoan(): " . ($user->hasActiveLoan() ? 'TRUE' : 'FALSE') . "\n";
