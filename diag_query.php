<?php

use App\Models\User;
use App\Models\Setting;
use App\Models\Scheme;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = Carbon::now()->format('Y-m');
$startOfMonth = Carbon::now()->startOfMonth();

echo "Diagnostic for Admin Charge Query\n";
echo "Current Time: " . Carbon::now()->toDateTimeString() . "\n";
echo "Start of Month: " . $startOfMonth->toDateTimeString() . "\n";

$query = User::whereNull('deceased_at')
    ->where(function ($query) use ($startOfMonth) {
        $query->whereNull('last_admin_charge_at')
              ->orWhere('last_admin_charge_at', '<', $startOfMonth);
    });

echo "Total count from query: " . $query->count() . "\n";
echo "SQL: " . $query->toSql() . "\n";
echo "Bindings: " . json_encode($query->getBindings()) . "\n";

$ids = $query->pluck('id')->toArray();
echo "IDs found: " . implode(', ', $ids) . "\n";

$missing = [1408, 1671, 1672, 1674, 1675, 2161];
foreach ($missing as $id) {
    $user = User::find($id);
    if ($user) {
        echo "User $id: Deceased=" . ($user->deceased_at ?? 'NULL') . ", LastCharge=" . ($user->last_admin_charge_at ?? 'NULL') . "\n";
        $matches = (is_null($user->deceased_at) && (is_null($user->last_admin_charge_at) || $user->last_admin_charge_at < $startOfMonth));
        echo "  Matches conditions manually? " . ($matches ? 'YES' : 'NO') . "\n";
    } else {
        echo "User $id: NOT FOUND\n";
    }
}
