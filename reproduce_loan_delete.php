<?php

use App\Models\User;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\LedgerJournal;
use App\Models\TransactionApproval;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::beginTransaction();

try {
    // 1. Setup a user
    $user = User::factory()->create();
    echo "Created user: {$user->id}\n";

    // 2. Create a loan with disbursement journal
    $disbursementJournal = LedgerJournal::create(['description' => 'Disbursement']);
    $loan = QardHasan::create([
        'user_id' => $user->id,
        'qard_id_string' => 'QH-DELETE-TEST-' . time(),
        'principal_amount' => 50000,
        'total_installments' => 5,
        'per_installment' => 10000,
        'interval' => 'monthly',
        'status' => 'active',
        'paid_amount' => 0,
        'ledger_journal_id' => $disbursementJournal->id,
    ]);
    echo "Created loan: {$loan->id} with journal {$disbursementJournal->id}\n";

    // 3. Create a repayment with its journal
    $repaymentJournal = LedgerJournal::create(['description' => 'Repayment']);
    $repayment = QardHasanRepayment::create([
        'qard_hasan_id' => $loan->id,
        'amount' => 10000,
        'reference' => 'REF-' . time(),
        'status' => 'success',
        'ledger_journal_id' => $repaymentJournal->id,
    ]);
    echo "Created repayment: {$repayment->id} with journal {$repaymentJournal->id}\n";

    // 4. Create a transaction approval
    $approval = TransactionApproval::create([
        'approvable_id' => $loan->id,
        'approvable_type' => QardHasan::class,
        'status' => 'approved',
    ]);
    echo "Created approval: {$approval->id}\n";

    // 5. Delete the loan
    echo "Deleting loan...\n";
    $loanId = $loan->id;
    $repaymentId = $repayment->id;
    $disbursementJournalId = $disbursementJournal->id;
    $repaymentJournalId = $repaymentJournal->id;
    $approvalId = $approval->id;

    $loan->delete();

    // 6. Verify cleanup
    $loanExists = QardHasan::find($loanId);
    $repaymentExists = QardHasanRepayment::find($repaymentId);
    $disbursementJournalExists = LedgerJournal::find($disbursementJournalId);
    $repaymentJournalExists = LedgerJournal::find($repaymentJournalId);
    $approvalExists = TransactionApproval::find($approvalId);

    if (!$loanExists) echo "✓ Loan deleted\n"; else echo "✗ Loan still exists!\n";
    if (!$repaymentExists) echo "✓ Repayment deleted\n"; else echo "✗ Repayment still exists!\n";
    if (!$disbursementJournalExists) echo "✓ Disbursement journal deleted\n"; else echo "✗ Disbursement journal still exists!\n";
    if (!$repaymentJournalExists) echo "✓ Repayment journal deleted\n"; else echo "✗ Repayment journal still exists!\n";
    if (!$approvalExists) echo "✓ Approval deleted\n"; else echo "✗ Approval still exists!\n";

    DB::rollBack();
    echo "Test completed successfully.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
