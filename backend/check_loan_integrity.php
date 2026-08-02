<?php

putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=33060'); // Based on FORWARD_DB_PORT=33060 in .env
putenv('CACHE_DRIVER=array');
putenv('SESSION_DRIVER=array');
putenv('QUEUE_CONNECTION=sync');
putenv('TELESCOPE_ENABLED=false');

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\Contribution;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use Illuminate\Support\Facades\DB;

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Starting Loan Balance Diagnostic...\n";

$loans = QardHasan::with('repayments')->get();

foreach ($loans as $loan) {
    $sumRepayments = (float) $loan->repayments()->where('status', 'success')->sum('amount');
    $diff = round($sumRepayments - (float) $loan->paid_amount, 2);

    if ($diff != 0) {
        echo "Loan ID: {$loan->id} ({$loan->qard_id_string}), User: {$loan->user_id}\n";
        echo "  - Principal: {$loan->principal_amount}\n";
        echo "  - Paid (field): {$loan->paid_amount}\n";
        echo "  - Sum of Repayments: {$sumRepayments}\n";
        echo "  - Difference: {$diff}\n";
    }
}

echo "\nChecking for Orphaned Loan Repayment Contributions...\n";

$orphans = Contribution::where('status', 'success')
    ->where('category', 'loan_repayment')
    ->whereDoesntHave('scheme', fn($q) => $q->where('name', 'Fine')) // Exclude fines if they were mislabeled
    ->get();

foreach ($orphans as $c) {
    $hasRepayment = QardHasanRepayment::where('reference', $c->reference)->exists();
    if (!$hasRepayment) {
        echo "Orphaned Contribution ID: {$c->id}, User: {$c->user_id}, Amount: {$c->amount}, Ref: {$c->reference}\n";
        // Check if there is an active loan to apply it to
        $loan = QardHasan::where('user_id', $c->user_id)
            ->whereIn('status', ['active', 'defaulted', 'completed']) // include completed in case it should have been completed earlier
            ->where('created_at', '<=', $c->created_at->addDays(1)) // heuristic to match roughly the time
            ->orderByDesc('created_at')
            ->first();
        if ($loan) {
            echo "  - Potential Loan: {$loan->id} ({$loan->qard_id_string}), Status: {$loan->status}\n";
        } else {
            echo "  - No Potential Loan found.\n";
        }
    }
}
