<?php

use App\Models\User;
use Carbon\Carbon;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$startOfMonth = Carbon::now()->startOfMonth();

$count = User::whereNull('deceased_at')
    ->where(function ($query) use ($startOfMonth) {
        $query->whereNull('last_admin_charge_at')
              ->orWhere('last_admin_charge_at', '<', $startOfMonth);
    })->count();

echo "Total eligible users: " . $count . "\n";

$missing = [1408, 1671, 1672, 1674, 1675, 2161];
foreach ($missing as $id) {
    $user = User::find($id);
    if ($user) {
        $eligible = is_null($user->deceased_at) && (is_null($user->last_admin_charge_at) || $user->last_admin_charge_at < $startOfMonth);
        echo "User $id: Eligible=" . ($eligible ? 'YES' : 'NO') . " (Last Charge: " . ($user->last_admin_charge_at ?? 'NULL') . ")\n";
    }
}
