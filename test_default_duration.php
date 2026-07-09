<?php

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

use App\Models\User;
use App\Models\QardHasan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::beginTransaction();

try {
    $user = User::factory()->create(['is_defaulter' => true]);
    
    // Create a defaulted loan
    $loan = QardHasan::create([
        'user_id' => $user->id,
        'qard_id_string' => 'TEST-LOAN-1',
        'principal_amount' => 100000,
        'total_installments' => 10,
        'per_installment' => 10000,
        'interval' => 'monthly',
        'status' => 'defaulted',
        'received_at' => Carbon::parse('2026-01-01'),
        'defaulted_at' => Carbon::parse('2026-03-01'),
        'paid_amount' => 20000,
    ]);

    echo "User Default Duration: " . $user->getDefaultDuration() . "\n";
    echo "Loan Period of Default: " . $loan->period_of_default . "\n";

    if (strpos($user->getDefaultDuration(), '4 months') !== false) {
        echo "SUCCESS: Duration matches expected value.\n";
    } else {
        echo "Check: Got " . $user->getDefaultDuration() . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
}
