<?php

use App\Services\CsvImportService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Override DB settings for local run outside Docker
config(['database.connections.mysql.host' => 'attaqwacoop']);
config(['database.connections.mysql.port' => '33060']);
config(['database.connections.mysql.database' => 'coop_attaqwa']);
config(['database.connections.mysql.username' => 'sail_attaqwa']);
config(['database.connections.mysql.password' => 'pass_attaqwa']);

$csvContent = "membership_number,qard_id_string,principal_amount,total_installments,per_installment,interval,admin_fee_flat,admin_fee_pct,paid_amount,status\n";
$csvContent .= "MEM001,QH-DEBUG-001,50000,10,5000,monthly,0,1,0,active\n";

$tempFile = tempnam(sys_get_temp_dir(), 'csv_debug');
file_put_contents($tempFile, $csvContent);

echo "Testing with CSV file: $tempFile\n";
echo "Content:\n$csvContent\n";

$svc = app(CsvImportService::class);

try {
    $res = $svc->importLoans($tempFile);
    echo "Result Summary:\n";
    print_r($res['summary']);
    if (!empty($res['errors'])) {
        echo "Errors:\n";
        print_r($res['errors']);
    }
} catch (\Throwable $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    unlink($tempFile);
}
