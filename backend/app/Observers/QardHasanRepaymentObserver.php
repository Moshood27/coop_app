<?php

namespace App\Observers;

use App\Models\QardHasanRepayment;
use App\Services\LedgerService;

class QardHasanRepaymentObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the QardHasanRepayment "updated" event.
     */
    public function updated(QardHasanRepayment $repayment): void
    {
        \Log::info("QardHasanRepaymentObserver: updated event for repayment #{$repayment->id}, status: {$repayment->status}");
        if ($repayment->wasChanged('status') && $repayment->status === 'success' && !$repayment->ledger_journal_id) {
            $this->recordToLedger($repayment);
        }
    }

    /**
     * Handle the QardHasanRepayment "created" event.
     */
    public function created(QardHasanRepayment $repayment): void
    {
        \Log::info("QardHasanRepaymentObserver: created event for repayment #{$repayment->id}, status: {$repayment->status}");
        if ($repayment->status === 'success' && !$repayment->ledger_journal_id) {
            $this->recordToLedger($repayment);
        }
    }

    protected function recordToLedger(QardHasanRepayment $repayment): void
    {
        try {
            \Log::info("QardHasanRepaymentObserver: Recording to ledger for repayment #{$repayment->id}");
            $journal = $this->ledgerService->recordLoanRepayment($repayment);
            $repayment->updateQuietly(['ledger_journal_id' => $journal->id]);
            \Log::info("QardHasanRepaymentObserver: Successfully recorded ledger journal #{$journal->id} for repayment #{$repayment->id}");
        } catch (\Exception $e) {
            \Log::error("Failed to record loan repayment in ledger: " . $e->getMessage());
        }
    }
}
