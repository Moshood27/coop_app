<?php

use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Contribution;
use App\Models\Scheme;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- October 2026 Admin Charge Diagnostic ---\n";

$today = '2026-10-01';
$startOfMonth = Carbon::parse($today)->startOfMonth();

// 1. Check for double debits
$doubleDebits = DB::table('wallet_transactions')
    ->select('user_id', DB::raw('count(*) as count'), DB::raw('SUM(amount) as total_amount'))
    ->where('source', 'admin_charge')
    ->whereDate('created_at', $today)
    ->groupBy('user_id')
    ->having('count', '>', 1)
    ->get();

echo "\nDouble Debited Users: " . $doubleDebits->count() . "\n";
foreach ($doubleDebits as $dd) {
    $user = User::find($dd->user_id);
    echo "User ID: {$dd->user_id}, Name: {$user->name}, Transactions: {$dd->count}, Total: {$dd->total_amount}\n";
}

// 2. Check for missing charges
$sittingScheme = Scheme::where('name', 'SITTING')->first();

$missingUsers = User::whereNull('deceased_at')
    ->where(function ($query) use ($startOfMonth) {
        $query->whereNull('last_admin_charge_at')
              ->orWhere('last_admin_charge_at', '<', $startOfMonth);
    })
    ->whereNotExists(function ($query) use ($today) {
        $query->select(DB::raw(1))
              ->from('wallet_transactions')
              ->whereColumn('wallet_transactions.user_id', 'users.id')
              ->where('source', 'admin_charge')
              ->whereDate('created_at', $today);
    })
    ->get();

echo "\nMissing Users (Eligible but not charged today): " . $missingUsers->count() . "\n";
// Display first 10 missing users
foreach ($missingUsers->take(10) as $mu) {
    echo "User ID: {$mu->id}, Name: {$mu->name}, Last Charge: " . ($mu->last_admin_charge_at ?? 'Never') . "\n";
}

if ($missingUsers->count() > 10) {
    echo "... and " . ($missingUsers->count() - 10) . " more.\n";
}
