<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$balanceColumns = [
    'ordinary_savings',
    'special_savings_balance',
    'shares_capital',
    'building_balance',
    'development_fund_balance',
    'agm_balance',
    'loan_repayment_balance',
    'fine_balance',
    'welfare_balance',
    'lateness_balance',
    'stationery_balance',
    'loan_form_balance',
    'others_balance',
    'id_card_balance',
    'emergency_balance',
    'entrance_balance',
    'h_savings_balance',
    'investment_balance',
    'group_savings_balance',
    'takaful_balance',
    'dawah_fund_balance',
    'sitting_balance',
    'gold_balance'
];

echo "Checking columns in 'users' table:\n";
foreach ($balanceColumns as $column) {
    if (Schema::hasColumn('users', $column)) {
        echo "[OK] $column exists.\n";
    } else {
        echo "[MISSING] $column is missing!\n";
    }
}
