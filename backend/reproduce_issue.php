<?php

use App\Models\QardHasan;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';
putenv('TELESCOPE_ENABLED=false');
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$loan = new QardHasan();
$reflector = new ReflectionClass($loan);
echo "Class file: " . $reflector->getFileName() . "\n";
$loan->principal_amount = 100000;
$loan->total_installments = 10;
$loan->per_installment = 10000;
$loan->interval = 'monthly';
$loan->status = 'active';

echo "Generating installment schedule...\n";
echo "Property exists: " . (property_exists($loan, 'installmentSchedule') ? 'Yes' : 'No') . "\n";
$reflector = new ReflectionClass($loan);
try {
    $prop = $reflector->getProperty('installmentSchedule');
    echo "Property visibility: " . ($prop->isProtected() ? 'protected' : 'other') . "\n";
} catch (ReflectionException $e) {
    echo "Property not found by reflection.\n";
}
try {
    $schedule = $loan->generateInstallmentSchedule();
    echo "Schedule generated successfully. Count: " . count($schedule) . "\n";
} catch (\Throwable $e) {
    echo "Caught exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "At: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
