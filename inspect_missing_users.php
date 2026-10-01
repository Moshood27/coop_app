<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Contribution;
use App\Models\Scheme;
use Carbon\Carbon;

$userIds = [1408, 1671, 1672, 1674, 1675, 2161];
$sittingScheme = Scheme::where('name', 'SITTING')->first();

foreach ($userIds as $id) {
    $user = User::find($id);
    if (!$user) {
        echo "User $id not found\n";
        continue;
    }
    
    echo "User $id ({$user->name}):\n";
    echo "  Deceased At: " . ($user->deceased_at ?? 'Null') . "\n";
    echo "  Last Admin Charge At: " . ($user->last_admin_charge_at ?? 'Null') . "\n";
    echo "  Admin Charge Balance: {$user->admin_charge_balance}\n";
    echo "  Wallet Balance: {$user->balance}\n";
    
    if ($sittingScheme) {
        $alreadyPaid = Contribution::where('user_id', $user->id)
            ->where('scheme_id', $sittingScheme->id)
            ->where('status', 'success')
            ->where('paid_at', '>=', Carbon::parse('2026-10-01')->startOfMonth())
            ->exists();
        echo "  Already Paid (SITTING) this month: " . ($alreadyPaid ? 'Yes' : 'No') . "\n";
    }
    echo "-------------------\n";
}
