<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WalletTransaction;
use App\Models\User;
use Carbon\Carbon;

$today = '2026-10-01';
$count = WalletTransaction::where('source', 'admin_charge')->whereDate('created_at', $today)->count();
echo "Total charges today: $count\n";

$missingCount = User::whereNull('deceased_at')
    ->where(function ($query) {
        $query->whereNull('last_admin_charge_at')
              ->orWhere('last_admin_charge_at', '<', Carbon::parse('2026-10-01')->startOfMonth());
    })
    ->count();
echo "Total missing users: $missingCount\n";
