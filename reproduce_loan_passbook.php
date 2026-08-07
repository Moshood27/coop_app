<?php

use App\Models\User;
use App\Models\QardHasan;
use App\Models\Scheme;
use App\Models\Contribution;
use App\Services\PassbookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Setup a user and a loan
DB::beginTransaction();

try {
    $user = User::factory()->create(['balance' => 10000]);
    $loanRepaymentScheme = Scheme::firstOrCreate(['name' => 'Loan Repayment'], ['active' => true]);

    $loan = QardHasan::create([
        'user_id' => $user->id,
        'principal_amount' => 5000,
        'paid_amount' => 0,
        'status' => 'active',
        'repayment_start_date' => now(),
    ]);

    echo "Created Loan ID: {$loan->id} for User ID: {$user->id}\n";

    // 2. Perform repayment via AdminMemberController logic (simulated)
    // (Mimicking AdminMemberController::loanRepayment)
    $amount = 1000;
    $method = 'cash';
    $paidAt = now();
    
    $loan->repayments()->create([
        'amount' => $amount,
        'payment_method' => $method,
        'reference' => 'TEST-QH-REP-' . strtoupper(Illuminate\Support\Str::random(12)),
        'paid_at' => $paidAt,
        'notes' => 'Test admin repayment',
        'status' => 'success',
    ]);
    $loan->increment('paid_amount', $amount);

    echo "Recorded repayment of {$amount} for Loan ID: {$loan->id}\n";

    // 3. Check Passbook
    $passbookService = app(PassbookService::class);
    $year = (int) date('Y');
    $passbookData = $passbookService->getPassbookData($user, $year);

    $loanRepaymentRow = null;
    foreach ($passbookData['matrix'] as $row) {
        if ($row['scheme_name'] === 'Loan Repayment') {
            $loanRepaymentRow = $row;
            break;
        }
    }

    if ($loanRepaymentRow) {
        echo "Loan Repayment row found in passbook.\n";
        echo "Total in passbook: {$loanRepaymentRow['total']}\n";
        if ($loanRepaymentRow['total'] == 0) {
            echo "RESULT: Loan repayment NOT reflected in passbook (Total is 0).\n";
        } else {
            echo "RESULT: Loan repayment reflected in passbook.\n";
        }
    } else {
        echo "RESULT: Loan Repayment scheme not found in passbook matrix.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
}
