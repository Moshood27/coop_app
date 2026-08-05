<?php

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

use App\Models\Contribution;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\User;
use App\Models\Scheme;
use Illuminate\Support\Facades\DB;

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Starting Integrity Repair...\n";

// 1. Identify and create missing repayment records from contributions
Contribution::where('status', 'success')
    ->where(function($q) {
        $q->where('category', 'loan_repayment')
          ->orWhereHas('scheme', fn($sq) => $sq->where('name', 'Loan Repayment'));
    })
    ->get()
    ->each(function($c) {
        if (!QardHasanRepayment::where('reference', $c->reference)->exists()) {
            $user = $c->user;
            if (!$user) return;

            $q = $c->qard_hasan_id ? QardHasan::find($c->qard_hasan_id) : null;
            
            if (!$q) {
                $q = QardHasan::where('user_id', $user->id)
                    ->whereIn('status', ['active', 'defaulted'])
                    ->whereColumn('paid_amount', '<', 'principal_amount')
                    ->first();
            }

            if ($q) {
                $before = (float) $q->paid_amount;
                $remaining = max(0, (float) $q->principal_amount - $before);
                $applied = round(min((float) $c->amount, $remaining), 2);
                
                if ($applied > 0) {
                    QardHasanRepayment::create([
                        'qard_hasan_id' => $q->id,
                        'amount' => $applied,
                        'payment_method' => $c->payment_method ?? 'contribution',
                        'reference' => $c->reference,
                        'status' => 'success',
                        'paid_at' => $c->paid_at ?? $c->created_at,
                        'notes' => 'Restored via repair script from contribution: ' . $c->id,
                    ]);
                    echo "Fixed: Created repayment for Contribution #{$c->id} (User: {$user->id}, Ref: {$c->reference})\n";
                }
            }
        }
    });

echo "Loan repayments repair completed.\n";

// 2. Final Sync of all Loan Balances
QardHasan::all()->each(function($q) {
    $actualPaid = (float) $q->repayments()->where('status', 'success')->sum('amount');
    if (abs((float)$q->paid_amount - $actualPaid) > 0.01) {
        $q->paid_amount = $actualPaid;
        if ($q->paid_amount >= $q->principal_amount && !in_array($q->status, ['completed', 'closed'])) {
            $q->status = 'completed';
        }
        $q->save();
        echo "Synced Loan #{$q->id} balance to {$actualPaid}\n";
    }
});

echo "Loan balances sync completed.\n";

// 3. Final Sync of User Scheme Balances
User::all()->each(function($u) {
    foreach (Scheme::pluck('name') as $name) {
        try {
            $u->syncSchemeBalance($name);
        } catch (\Exception $e) {
            // Skip errors
        }
    }
});

echo "Member balances sync completed.\n";
echo "Integrity check and repair completed successfully.\n";
