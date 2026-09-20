<?php

use App\Models\User;
use App\Models\QardHasan;
use App\Models\Scheme;
use App\Models\Contribution;
use App\Models\WalletTransaction;
use App\Services\PassbookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
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

    // --- CASE 1: Wallet Allocation (Member Action) ---
    echo "\n--- CASE 1: Wallet Allocation ---\n";
    $amount1 = 1200;
    $reference1 = 'WAL-ALLOC-' . Str::random(10);
    
    // Simulate WalletController::allocateToSchemes logic
    $contribution1 = Contribution::create([
        'user_id' => $user->id,
        'scheme_id' => $loanRepaymentScheme->id,
        'amount' => $amount1,
        'reference' => $reference1,
        'status' => 'success',
        'category' => 'loan_repayment',
        'paid_at' => now(),
    ]);

    echo "Created Contribution from Wallet: ID {$contribution1->id}, Reference: {$reference1}\n";
    
    // Check if QardHasanRepayment was created by observer
    $repayment1 = \App\Models\QardHasanRepayment::where('reference', $reference1)->first();
    if ($repayment1) {
        echo "SUCCESS: QardHasanRepayment created automatically for wallet allocation.\n";
        echo "Repayment amount: {$repayment1->amount}, Loan ID: {$repayment1->qard_hasan_id}\n";
    } else {
        echo "FAILURE: QardHasanRepayment NOT created for wallet allocation.\n";
    }
    
    // Check if Contribution has qard_hasan_id set
    $contribution1->refresh();
    echo "Contribution qard_hasan_id: " . ($contribution1->qard_hasan_id ?: 'NULL') . "\n";

    // --- CASE 2: Admin Manual Repayment ---
    echo "\n--- CASE 2: Admin Manual Repayment ---\n";
    $amount2 = 800;
    // Simulate AdminMemberController::loanRepayment logic
    $loan->repayments()->create([
        'amount' => $amount2,
        'payment_method' => 'cash',
        'reference' => 'ADM-REP-' . Str::random(10),
        'paid_at' => now(),
        'status' => 'success',
    ]);
    $loan->increment('paid_amount', $amount2);
    echo "Recorded Admin Manual Repayment of {$amount2}\n";

    // 3. Check Passbook
    echo "\n--- Passbook Analysis ---\n";
    $passbookService = app(PassbookService::class);
    $year = (int) date('Y');
    $passbookData = $passbookService->getPassbookData($user, $year);

    echo "Grand Total in Passbook: {$passbookData['grand_total']}\n";
    
    foreach ($passbookData['matrix'] as $row) {
        echo "Row: {$row['scheme_name']}, Total: {$row['total']}, Exceptional: " . ($row['is_exceptional'] ? 'YES' : 'NO') . "\n";
        if (strpos($row['scheme_name'], 'Loan Repayment') !== false) {
            $months = array_filter($row['months']);
            echo "  Months with data: " . json_encode($months) . "\n";
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
} finally {
    DB::rollBack();
}
