<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Adjust DB config if running outside docker but connecting to docker
if (getenv('DB_HOST') === false) {
    config(['database.connections.mysql.host' => '127.0.0.1']);
    config(['database.connections.mysql.port' => '33060']);
    config(['database.connections.mysql.username' => 'sail_attaqwa']);
    config(['database.connections.mysql.password' => 'pass_attaqwa']);
}

try {
    echo "Checking users table...\n";
    $totalUsers = DB::table('users')->count();
    echo "Total users: $totalUsers\n";

    $autosaveEnabledCount = DB::table('users')->where('autosave_enabled', true)->count();
    echo "Users with autosave_enabled=true: $autosaveEnabledCount\n";

    $emailNotNullCount = DB::table('users')->whereNotNull('email')->count();
    echo "Users with email not null: $emailNotNullCount\n";

    echo "\nChecking user_virtual_accounts table...\n";
    $totalVAs = DB::table('user_virtual_accounts')->count();
    echo "Total virtual accounts: $totalVAs\n";

    $authCodeNotNullCount = DB::table('user_virtual_accounts')->whereNotNull('paystack_authorization_code')->count();
    echo "VAs with paystack_authorization_code not null: $authCodeNotNullCount\n";

    echo "\nChecking combined criteria...\n";
    $combined = DB::table('users')
        ->join('user_virtual_accounts', 'users.id', '=', 'user_virtual_accounts.user_id')
        ->where('users.autosave_enabled', 1)
        ->whereNotNull('users.email')
        ->whereNotNull('user_virtual_accounts.paystack_authorization_code')
        ->count();
    echo "Users matching all criteria: $combined\n";

    if ($combined === 0) {
        echo "\nSample user data (autosave_enabled=true):\n";
        $samples = DB::table('users')->where('autosave_enabled', true)->limit(5)->get();
        foreach ($samples as $sample) {
            $va = DB::table('user_virtual_accounts')->where('user_id', $sample->id)->first();
            echo "User ID: {$sample->id}, Email: {$sample->email}, Has VA: " . ($va ? 'Yes' : 'No') . ", Auth Code: " . ($va?->paystack_authorization_code ?? 'NULL') . "\n";
        }
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
