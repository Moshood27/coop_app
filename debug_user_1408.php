<?php

use App\Models\User;
use App\Models\Scheme;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userId = 1408;
$user = User::find($userId);

if (!$user) {
    echo "User $userId not found.\n";
    exit;
}

echo "User $userId details:\n";
echo "  Name: {$user->name}\n";
echo "  Deceased At: " . ($user->deceased_at ?? 'NULL') . "\n";
echo "  Last Admin Charge At: " . ($user->last_admin_charge_at ?? 'NULL') . "\n";
echo "  Is Distant: " . ($user->is_distant ? 'Yes' : 'No') . "\n";
echo "  Wallet Balance: {$user->balance}\n";
echo "  Admin Charge Balance: {$user->admin_charge_balance}\n";

$period = Carbon::now()->format('Y-m');
$startOfMonth = Carbon::now()->startOfMonth();

echo "\nQuery Checks:\n";
echo "  1. Deceased At is NULL: " . (is_null($user->deceased_at) ? 'PASS' : 'FAIL') . "\n";
$lastCharge = $user->last_admin_charge_at ? Carbon::parse($user->last_admin_charge_at) : null;
$needsCharge = is_null($lastCharge) || $lastCharge->lt($startOfMonth);
echo "  2. Needs Charge (NULL or < $startOfMonth): " . ($needsCharge ? 'PASS' : 'FAIL') . "\n";

$query = User::whereNull('deceased_at')
    ->where(function ($query) use ($startOfMonth) {
        $query->whereNull('last_admin_charge_at')
              ->orWhere('last_admin_charge_at', '<', $startOfMonth);
    });

$existsInQuery = (clone $query)->where('id', $userId)->exists();
echo "  3. Exists in Accrual Query: " . ($existsInQuery ? 'YES' : 'NO') . "\n";

$totalEligible = $query->count();
echo "\nTotal Eligible Users according to query: $totalEligible\n";

// Check if there are any users with higher IDs that were processed
$maxProcessedIdToday = DB::table('wallet_transactions')
    ->where('source', 'admin_charge')
    ->whereDate('created_at', '2026-10-01')
    ->max('user_id');

$lastAdminChargeAtMax = User::whereNotNull('last_admin_charge_at')
    ->where('last_admin_charge_at', '>=', $startOfMonth)
    ->max('id');

echo "Max User ID processed/updated today: " . max($maxProcessedIdToday, $lastAdminChargeAtMax) . "\n";
