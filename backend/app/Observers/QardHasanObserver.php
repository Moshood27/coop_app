<?php

namespace App\Observers;

use App\Models\QardHasan;
use App\Services\LedgerService;

class QardHasanObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the QardHasan "updated" event.
     */
    public function updated(QardHasan $loan): void
    {
        if ($loan->wasChanged('status') && $loan->status === 'active' && !$loan->ledger_journal_id) {
            $this->recordToLedger($loan);
        }
    }

    /**
     * Handle the QardHasan "created" event.
     */
    public function created(QardHasan $loan): void
    {
        if ($loan->status === 'active' && !$loan->ledger_journal_id) {
            $this->recordToLedger($loan);
        }
    }

    protected function recordToLedger(QardHasan $loan): void
    {
        try {
            $journal = $this->ledgerService->recordLoanDisbursement($loan);
            $loan->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Exception $e) {
            \Log::error("Failed to record loan disbursement in ledger: " . $e->getMessage());
        }
    }
}
