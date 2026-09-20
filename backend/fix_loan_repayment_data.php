<?php

/**
 * Sync and Fix script for Loan Repayments and Passbook
 * Run on VPS: php fix_loan_repayment_data.php
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
use Illuminate\Support\Carbon;

$loanScheme = Scheme::where('name', 'Loan Repayment')->first();

if (!$loanScheme) {
    echo "ERROR: 'Loan Repayment' scheme not found.\n";
    exit(1);
}

echo "Starting Sync Process...\n";

// --- PART 1: Fix existing Contributions (Wallet Allocations) ---
echo "\nChecking for misconfigured Contributions...\n";

$contributions = Contribution::where('scheme_id', $loanScheme->id)
    ->where(function($q) {
        $q->whereNull('qard_hasan_id')
          ->orWhere('category', '!=', 'loan_repayment');
    })
    ->where('status', 'success')
    ->with('user')
    ->get();

echo "Found " . $contributions->count() . " contributions to fix.\n";

$fixedCount = 0;
foreach ($contributions as $con) {
    echo "Processing Contribution ID: {$con->id} (Ref: {$con->reference}, User: {$con->user->full_name})\n";

    DB::beginTransaction();
    try {
        // 1. Ensure category is 'loan_repayment'
        if ($con->category !== 'loan_repayment') {
            $con->category = 'loan_repayment';
            echo "  - Updated category to 'loan_repayment'\n";
        }

        // 2. Find associated repayment record by reference
        $repayment = QardHasanRepayment::where('reference', $con->reference)->first();

        $loanId = $con->qard_hasan_id;

        if ($repayment) {
            echo "  - Found existing repayment record (Loan ID: {$repayment->qard_hasan_id})\n";
            $loanId = $repayment->qard_hasan_id;
        } else {
            // Try to find an active loan for the user to link
            $loan = QardHasan::where('user_id', $con->user_id)
                ->whereIn('status', ['active', 'defaulted'])
                ->whereColumn('paid_amount', '<', 'principal_amount')
                ->first();

            if (!$loan) {
                $loan = QardHasan::where('user_id', $con->user_id)
                    ->orderByDesc('updated_at')
                    ->first();
            }

            if ($loan) {
                $loanId = $loan->id;
                $repayment = QardHasanRepayment::create([
                    'qard_hasan_id' => $loan->id,
                    'amount' => $con->amount,
                    'payment_method' => $con->payment_method ?: 'wallet',
                    'reference' => $con->reference,
                    'status' => 'success',
                    'paid_at' => $con->paid_at ?: $con->created_at,
                    'notes' => $con->notes ?: 'Repayment via Wallet (Synced)',
                ]);
                echo "  - Created missing repayment record for Loan ID: {$loan->id}\n";

                // Only update paid_amount if we just created the repayment
                $loan->increment('paid_amount', (float)$con->amount);
                if ($loan->paid_amount >= $loan->principal_amount) {
                    $loan->update(['status' => 'completed']);
                }
            }
        }

        // 3. Link contribution to loan for Passbook visibility
        if ($loanId && $con->qard_hasan_id != $loanId) {
            $con->qard_hasan_id = $loanId;
            echo "  - Linked contribution to Loan ID: {$loanId}\n";
        }

        $con->save();
        DB::commit();
        $fixedCount++;
    } catch (\Exception $e) {
        DB::rollBack();
        echo "  - Error: " . $e->getMessage() . "\n";
    }
}

// --- PART 2: Create missing Contributions (Manual Admin Repayments) ---
echo "\nChecking for Repayments without Passbook entries...\n";

// Find all success repayments that don't have a matching contribution by reference
$repayments = QardHasanRepayment::where('status', 'success')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
              ->from('contributions')
              ->whereColumn('contributions.reference', 'qard_hasan_repayments.reference');
    })
    ->with('loan.user')
    ->get();

echo "Found " . $repayments->count() . " repayments to sync to Passbook.\n";

$syncedCount = 0;
foreach ($repayments as $rep) {
    if (!$rep->loan || !$rep->loan->user) {
        echo "Skipping repayment ID {$rep->id}: Loan or User not found.\n";
        continue;
    }

    echo "Syncing Repayment ID: {$rep->id} (Ref: {$rep->reference}, User: {$rep->loan->user->full_name})\n";

    DB::beginTransaction();
    try {
        Contribution::create([
            'user_id' => $rep->loan->user_id,
            'scheme_id' => $loanScheme->id,
            'amount' => $rep->amount,
            'status' => 'success',
            'paid_at' => $rep->paid_at ?: $rep->created_at,
            'payment_method' => $rep->payment_method ?: 'cash',
            'reference' => $rep->reference,
            'category' => 'loan_repayment',
            'qard_hasan_id' => $rep->qard_hasan_id,
            'notes' => $rep->notes ?: "Repayment for Loan QH-{$rep->qard_hasan_id} (Synced)",
        ]);

        DB::commit();
        echo "  - Created Contribution record for Passbook.\n";
        $syncedCount++;
    } catch (\Exception $e) {
        DB::rollBack();
        echo "  - Error: " . $e->getMessage() . "\n";
    }
}

echo "\n--- Summary ---\n";
echo "Fixed Contributions: $fixedCount\n";
echo "Synced Repayments to Passbook: $syncedCount\n";
echo "Sync Process Completed.\n";
