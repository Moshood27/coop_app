<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\QardHasan;

$userId = 1784;
$user = User::find($userId);

if (!$user) {
    echo "User $userId not found.\n";
    exit;
}

echo "User: " . $user->full_name . " (ID: " . $user->id . ")\n";
echo "Has Active Loan: " . ($user->hasActiveLoan() ? 'Yes' : 'No') . "\n";

$loans = QardHasan::where('user_id', $userId)->get();
echo "Total Loans: " . $loans->count() . "\n";

foreach ($loans as $loan) {
    echo "Loan ID: " . $loan->id . "\n";
    echo "  Status: " . $loan->status . "\n";
    echo "  Principal: " . $loan->principal_amount . "\n";
    echo "  Paid: " . $loan->paid_amount . "\n";
    echo "  Created at: " . $loan->created_at . "\n";
}
