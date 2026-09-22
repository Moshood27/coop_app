<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Verifying database columns...\n";
    $columns = DB::select("SHOW COLUMNS FROM users LIKE 'dawah_fund_balance'");
    if (count($columns) > 0) {
        echo "[OK] dawah_fund_balance exists.\n";
    } else {
        echo "[FAIL] dawah_fund_balance still missing!\n";
    }

    $columns = DB::select("SHOW COLUMNS FROM users LIKE 'sitting_balance'");
    if (count($columns) > 0) {
        echo "[OK] sitting_balance exists.\n";
    } else {
        echo "[FAIL] sitting_balance still missing!\n";
    }

    echo "Testing syncSchemeBalance...\n";
    $user = User::first();
    if ($user) {
        echo "Syncing 'Dawah Fund' for user ID: {$user->id}...\n";
        $user->syncSchemeBalance('Dawah Fund');
        echo "[OK] syncSchemeBalance('Dawah Fund') executed successfully.\n";

        echo "Syncing 'SITTING' for user ID: {$user->id}...\n";
        $user->syncSchemeBalance('SITTING');
        echo "[OK] syncSchemeBalance('SITTING') executed successfully.\n";

        $user->refresh();
        echo "Dawah Fund Balance: {$user->dawah_fund_balance}\n";
        echo "Sitting Balance: {$user->sitting_balance}\n";
    } else {
        echo "No user found to test with.\n";
    }

} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
