<?php

use App\Models\QardHasan;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = QardHasan::where('total_installments', '>', 1200)->count();
echo "Loans with > 1200 installments: $count\n";

$loans = QardHasan::where('total_installments', '>', 1200)->get(['id', 'total_installments', 'principal_amount', 'status']);
foreach ($loans as $l) {
    echo "ID: {$l->id}, Installments: {$l->total_installments}, Principal: {$l->principal_amount}, Status: {$l->status}\n";
}
