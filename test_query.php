<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = Carbon::now()->format('Y-m');
$startOfMonth = Carbon::now()->startOfMonth();

$query = User::whereNull('deceased_at')
    ->where(function ($query) use ($startOfMonth) {
        $query->whereNull('last_admin_charge_at')
              ->orWhere('last_admin_charge_at', '<', $startOfMonth);
    });

echo "Total Eligible: " . $query->count() . "\n";

$allIds = $query->pluck('id')->toArray();
sort($allIds);

echo "IDs: " . implode(', ', $allIds) . "\n";

// Check for missing users
$missing = [1408, 1671, 1672, 1674, 1675, 2161];
foreach ($missing as $id) {
    echo "ID $id in results? " . (in_array($id, $allIds) ? 'YES' : 'NO') . "\n";
}
