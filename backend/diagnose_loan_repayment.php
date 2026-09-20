<?php

/**
 * Diagnostic script for Loan Repayments via Wallet
 * Run on VPS: php diagnose_loan_repayment.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Contribution;
use App\Models\Scheme;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use Illuminate\Support\Facades\DB;

$loanScheme = Scheme::where('name', 'Loan Repayment')->first();

if (!$loanScheme) {
    echo "ERROR: 'Loan Repayment' scheme not found.\n";
    exit(1);
}

echo "Found 'Loan Repayment' scheme ID: {$loanScheme->id}\n";

// 1. Find contributions for this scheme in Sept 2026
$startDate = '2026-09-01 00:00:00';
$endDate = '2026-09-30 23:59:59';

$contributions = Contribution::where('scheme_id', $loanScheme->id)
    ->whereBetween('paid_at', [$startDate, $endDate])
    ->with('user')
    ->get();

echo "Found " . $contributions->count() . " contributions for 'Loan Repayment' in September 2026.\n";

foreach ($contributions as $con) {
    echo "--- Contribution ID: {$con->id} ---\n";
    echo "  User: {$con->user->full_name} (ID: {$con->user_id})\n";
    echo "  Amount: {$con->amount}\n";
    echo "  Reference: {$con->reference}\n";
    echo "  Category: " . ($con->category ?: 'NULL') . "\n";
    echo "  Qard Hasan ID: " . ($con->qard_hasan_id ?: 'NULL') . "\n";
    echo "  Status: {$con->status}\n";

    // Check if a repayment record exists
    $repayment = QardHasanRepayment::where('reference', $con->reference)->first();
    if ($repayment) {
        echo "  Repayment Record Found: ID {$repayment->id}, Loan ID: {$repayment->qard_hasan_id}\n";
        if (!$con->qard_hasan_id) {
            echo "  !!! MISSING LINK: Contribution has no qard_hasan_id but repayment record exists.\n";
        }
    } else {
        echo "  !!! MISSING REPAYMENT: No QardHasanRepayment record found for this reference.\n";

        // Find possible loans for this user
        $loans = QardHasan::where('user_id', $con->user_id)
            ->whereIn('status', ['active', 'defaulted'])
            ->get();

        if ($loans->isEmpty()) {
            echo "  No active/defaulted loans found for this user.\n";
        } else {
            echo "  Possible loans for user:\n";
            foreach ($loans as $loan) {
                echo "    - Loan ID: {$loan->id}, Principal: {$loan->principal_amount}, Paid: {$loan->paid_amount}\n";
            }
        }
    }
}

echo "\n--- Summary ---\n";
$unlinkedCount = Contribution::where('scheme_id', $loanScheme->id)->whereNull('qard_hasan_id')->count();
echo "Total unlinked 'Loan Repayment' contributions (all time): $unlinkedCount\n";

$wrongCategoryCount = Contribution::where('scheme_id', $loanScheme->id)->where('category', '!=', 'loan_repayment')->count();
echo "Total 'Loan Repayment' contributions with wrong category (all time): $wrongCategoryCount\n";
