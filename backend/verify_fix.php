<?php

use App\Models\User;
use App\Models\QardHasan;
use App\Models\Scheme;
use App\Models\Contribution;
use App\Models\QardHasanRepayment;
use App\Services\PassbookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Setup a user and a loan
DB::beginTransaction();

try {
    $user = User::factory()->create(['balance' => 10000, 'surname' => 'Test', 'name' => 'User']);
    $loanRepaymentScheme = Scheme::firstOrCreate(['name' => 'Loan Repayment'], ['active' => true]);

    $loan = QardHasan::create([
        'user_id' => $user->id,
        'principal_amount' => 5000,
        'paid_amount' => 0,
        'status' => 'active',
        'repayment_start_date' => now(),
    ]);

    echo "Created Loan ID: {$loan->id} for User ID: {$user->id}\n";

    // --- TEST CASE: Wallet Allocation mimicking member action ---
    // Member action via WalletController::allocateToSchemes sets category to 'deposit' by default
    echo "\n--- SIMULATING WALLET ALLOCATION (MEMBER ACTION) ---\n";
    $amount = 1500;
    $reference = 'WAL-ALLOC-' . Str::random(10);

    $contribution = Contribution::create([
        'user_id' => $user->id,
        'scheme_id' => $loanRepaymentScheme->id,
        'amount' => $amount,
        'reference' => $reference,
        'status' => 'success',
        'category' => 'deposit', // explicitly 'deposit' as WalletController does
        'paid_at' => now(),
    ]);

    $contribution->refresh();
    echo "Contribution Created - ID: {$contribution->id}, Category: {$contribution->category}, Qard Hasan ID: " . ($contribution->qard_hasan_id ?: 'NULL') . "\n";

    if ($contribution->category === 'loan_repayment') {
        echo "SUCCESS: Category auto-inferred as 'loan_repayment' even though 'deposit' was passed.\n";
    } else {
        echo "FAILURE: Category NOT auto-inferred correctly.\n";
    }

    if ($contribution->qard_hasan_id == $loan->id) {
        echo "SUCCESS: Contribution linked to Loan ID {$loan->id}.\n";
    } else {
        echo "FAILURE: Contribution NOT linked to loan.\n";
    }

    // Check if repayment record exists
    $repayment = QardHasanRepayment::where('reference', $reference)->first();
    if ($repayment) {
        echo "SUCCESS: QardHasanRepayment created. Amount: {$repayment->amount}\n";
    } else {
        echo "FAILURE: QardHasanRepayment NOT created.\n";
    }

    $loan->refresh();
    echo "Loan Paid Amount: {$loan->paid_amount}\n";

    // 2. Check Passbook Service
    echo "\n--- VERIFYING PASSBOOK DATA ---\n";
    $passbookService = app(PassbookService::class);
    $year = (int) date('Y');
    $passbookData = $passbookService->getPassbookData($user, $year);

    $foundInMatrix = false;
    foreach ($passbookData['matrix'] as $row) {
        if (strpos($row['scheme_name'], "QH-{$loan->id}") !== false || strpos($row['scheme_name'], $loan->qard_id_string ?: 'Loan:') !== false) {
            $foundInMatrix = true;
            $septTotal = $row['months'][9] ?? 0; // September is 9th month if start is Jan
            echo "Found row in Passbook: {$row['scheme_name']}, Sept Total: {$septTotal}\n";
            if ($septTotal == $amount) {
                echo "SUCCESS: Repayment appears in the correct row for September.\n";
            } else {
                echo "FAILURE: Repayment amount mismatch in matrix.\n";
            }
        }
    }

    if (!$foundInMatrix) {
        echo "FAILURE: Loan repayment row NOT found in passbook matrix.\n";
        // Check "Loan Repayment (Other)"
        foreach ($passbookData['matrix'] as $row) {
            if ($row['scheme_name'] === 'Loan Repayment (Other)') {
                echo "Found in 'Loan Repayment (Other)' instead. Total: {$row['total']}\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    DB::rollBack();
}
