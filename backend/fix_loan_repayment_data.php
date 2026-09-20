<?php

/**
 * Data Fix script for Loan Repayments via Wallet
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

$loanScheme = Scheme::where('name', 'Loan Repayment')->first();

if (!$loanScheme) {
    echo "ERROR: 'Loan Repayment' scheme not found.\n";
    exit(1);
}

// Find unlinked or miscategorized contributions
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

        // 2. Find or create repayment record
        $repayment = QardHasanRepayment::where('reference', $con->reference)->first();

        $loanId = $con->qard_hasan_id;

        if ($repayment) {
            echo "  - Found existing repayment record (Loan ID: {$repayment->qard_hasan_id})\n";
            $loanId = $repayment->qard_hasan_id;
        } else {
            // Try to find an active loan for the user
            $loan = QardHasan::where('user_id', $con->user_id)
                ->whereIn('status', ['active', 'defaulted'])
                ->whereColumn('paid_amount', '<', 'principal_amount')
                ->first();

            if (!$loan) {
                // Check if there was a loan that just completed?
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
                    'notes' => $con->notes ?: 'Repayment via Wallet (Fixed)',
                ]);
                echo "  - Created new repayment record for Loan ID: {$loan->id}\n";

                // Update loan paid amount if not already accounted
                // We should be careful here not to double count.
                // But QardHasanRepayment creation usually happens via observer.
                // If it was MISSING, we should update the loan.
                $loan->increment('paid_amount', (float)$con->amount);
                if ($loan->paid_amount >= $loan->principal_amount) {
                    $loan->update(['status' => 'completed']);
                }
            } else {
                echo "  - Warning: No loan found for user ID: {$con->user_id}. Skipping linkage.\n";
            }
        }

        // 3. Link contribution to loan
        if ($loanId && $con->qard_hasan_id != $loanId) {
            $con->qard_hasan_id = $loanId;
            echo "  - Linked contribution to Loan ID: {$loanId}\n";
        }

        $con->save();
        DB::commit();
        $fixedCount++;
    } catch (\Exception $e) {
        DB::rollBack();
        echo "  - Error processing contribution {$con->id}: " . $e->getMessage() . "\n";
    }
}

echo "\nFixed $fixedCount contributions.\n";
