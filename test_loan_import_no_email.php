<?php

use App\Services\CsvImportService;
use App\Models\User;
use App\Models\QardHasan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Override DB settings for local run outside Docker
config(['database.connections.mysql.host' => '127.0.0.1']);
config(['database.connections.mysql.port' => '33060']);
config(['database.connections.mysql.database' => 'coop_attaqwa']);
config(['database.connections.mysql.username' => 'sail_attaqwa']);
config(['database.connections.mysql.password' => 'pass_attaqwa']);

// Create a test user
$user = User::updateOrCreate(
    ['membership_number' => 'MEM001'],
    [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]
);

echo "User ID: " . $user->id . "\n";

$csvContent = "membership_number,qard_id_string,principal_amount,total_installments,per_installment,interval,admin_fee_flat,admin_fee_pct,paid_amount,status\n";
$csvContent .= "MEM001,QH-2026-TEST,50000,10,5000,monthly,0,1,0,active\n";

$tempFile = tempnam(sys_get_temp_dir(), 'csv');
file_put_contents($tempFile, $csvContent);

$svc = app(CsvImportService::class);

DB::beginTransaction();

try {
    $res = $svc->importLoans($tempFile);
    print_r($res['summary']);
    
    if (!empty($res['errors'])) {
        print_r($res['errors']);
    }

    $loan = QardHasan::where('qard_id_string', 'QH-2026-TEST')->first();
    if ($loan) {
        echo "Loan successfully imported without email column.\n";
        echo "User ID on loan: " . $loan->user_id . "\n";
    } else {
        echo "Failed to import loan.\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
    unlink($tempFile);
}
