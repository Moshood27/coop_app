<?php

use App\Models\QardHasan;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$loan = QardHasan::find(2569);
if ($loan) {
    echo "Loan 2569:\n";
    echo "Total Installments: " . $loan->total_installments . "\n";
    echo "Principal: " . $loan->principal_amount . "\n";
    echo "Status: " . $loan->status . "\n";
} else {
    echo "Loan 2569 not found.\n";
}
