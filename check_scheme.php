<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Scheme;

$scheme = Scheme::where('name', 'Loan Repayment')->first();
if ($scheme) {
    echo "Found scheme: " . $scheme->name . " (ID: " . $scheme->id . ")\n";
} else {
    echo "Loan Repayment scheme not found.\n";
}
