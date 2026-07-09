<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking Sitting Fee Settings:\n";
$sittingFee = Setting::get('sitting_fee_amount');
$monthlyFeesEnabled = Setting::get('monthly_fees_enabled');
$meetingFee = Setting::get('meeting_fee_amount');

echo "monthly_fees_enabled: " . var_export($monthlyFeesEnabled, true) . "\n";
echo "sitting_fee_amount: " . var_export($sittingFee, true) . " (Default in code: 300)\n";
echo "meeting_fee_amount: " . var_export($meetingFee, true) . " (Default in code: 1000)\n";

echo "\nChecking User Auto-Deduct Status (First 10 users):\n";
$users = User::limit(10)->get();
foreach ($users as $user) {
    echo "User: {$user->name} ({$user->membership_number}) - Distant: " . ($user->is_distant ? 'Yes' : 'No') . " - Auto-Deduct: " . ($user->admin_charge_auto_deduct ? 'Yes' : 'No') . "\n";
}

$autoDeductCount = User::where('admin_charge_auto_deduct', true)->count();
$totalUsers = User::count();
echo "\nTotal Users: $totalUsers\n";
echo "Users with Auto-Deduct enabled: $autoDeductCount\n";
