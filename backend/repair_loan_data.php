<?php

putenv('CACHE_DRIVER=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=sync');
putenv('TELESCOPE_ENABLED=false');

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Contribution;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Starting Historical Data Repair...\n";

// 1. Identify Loan Repayment and Fine schemes
$loanRepaymentScheme = Scheme::where('name', 'Loan Repayment')->first();
$fineScheme = Scheme::where('name', 'Fine')->first();

if (!$loanRepaymentScheme) {
    echo "Warning: 'Loan Repayment' scheme not found.\n";
}
if (!$fineScheme) {
    echo "Warning: 'Fine' scheme not found.\n";
}

// 2. Fix Categories for historical contributions
echo "\nFixing categories for historical contributions...\n";
$contributionsToFix = Contribution::where('status', 'success')
    ->where(function($q) use ($loanRepaymentScheme, $fineScheme) {
        if ($loanRepaymentScheme) $q->where('scheme_id', $loanRepaymentScheme->id);
        if ($fineScheme) $q->orWhere('scheme_id', $fineScheme->id);
    })
    ->whereIn('category', ['deposit', '', null])
    ->get();

echo "Found " . $contributionsToFix->count() . " contributions to fix.\n";

foreach ($contributionsToFix as $c) {
    $schemeName = $c->scheme->name;
    if ($schemeName === 'Loan Repayment') {
        $c->category = 'loan_repayment';
    } elseif ($schemeName === 'Fine') {
        $c->category = 'fine';
    }

    if ($c->isDirty('category')) {
        echo "Updating Contribution ID: {$c->id}, User: {$c->user_id}, Scheme: {$schemeName}, Amount: {$c->amount}\n";
        $c->save(); // This will NOT trigger the repayment logic in updated() because status/amount didn't change
    }
}

// 3. Reconcile Loan Balances
echo "\nReconciling Loan Balances (Paid Amount)...\n";

// First, ensure all 'loan_repayment' contributions have corresponding QardHasanRepayment records
$repaymentContributions = Contribution::where('status', 'success')
    ->where('category', 'loan_repayment')
    ->get();

foreach ($repaymentContributions as $c) {
    if (!QardHasanRepayment::where('reference', $c->reference)->exists()) {
        echo "Missing Repayment Record for Contribution ID: {$c->id}, Amount: {$c->amount}, Ref: {$c->reference}\n";

        // Try to find the loan
        $q = null;
        if ($c->qard_hasan_id) {
            $q = QardHasan::find($c->qard_hasan_id);
        }
        if (!$q) {
            $q = QardHasan::where('user_id', $c->user_id)
                ->whereIn('status', ['active', 'defaulted', 'completed'])
                ->where('created_at', '<=', $c->created_at->addDays(1))
                ->orderByDesc('created_at')
                ->first();
        }

        if ($q) {
            echo "  - Applying to Loan ID: {$q->id} ({$q->qard_id_string})\n";
            QardHasanRepayment::create([
                'qard_hasan_id' => $q->id,
                'amount' => $c->amount,
                'payment_method' => $c->payment_method ?? 'contribution',
                'reference' => $c->reference,
                'status' => 'success',
                'paid_at' => $c->paid_at ?? $c->created_at,
                'notes' => 'Restored via repair script from contribution: ' . $c->id,
            ]);
        } else {
            echo "  - Could not find a suitable loan for user {$c->user_id}\n";
        }
    }
}

// Now recalculate 'paid_amount' for all loans based on successful repayments
$loans = QardHasan::all();
foreach ($loans as $loan) {
    $actualPaid = (float) $loan->repayments()->where('status', 'success')->sum('amount');
    if (round($actualPaid, 2) != round((float) $loan->paid_amount, 2)) {
        echo "Updating Loan ID: {$loan->id} ({$loan->qard_id_string}), Old Paid: {$loan->paid_amount}, New Paid: {$actualPaid}\n";
        $loan->paid_amount = $actualPaid;
        if ($loan->paid_amount >= $loan->principal_amount && $loan->principal_amount > 0) {
            if (!in_array($loan->status, ['cancelled', 'rejected'])) {
                $loan->status = 'completed';
            }
        } elseif ($loan->status === 'completed' && $loan->paid_amount < $loan->principal_amount) {
            $loan->status = 'active'; // Re-activate if underpaid
        }
        $loan->save();
    }
}

// 4. Reconcile User Fine Balances
echo "\nReconciling User Fine Balances...\n";
$users = User::where('outstanding_fines', '>', 0)->orWhereHas('contributions', fn($q) => $q->where('category', 'fine'))->get();
foreach ($users as $user) {
    // This is more complex because fines are added from attendance.
    // But we can at least sync the fine_balance scheme.
    $user->syncSchemeBalance('Fine');
}

echo "\nRepair Complete.\n";
