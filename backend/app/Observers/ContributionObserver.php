<?php

namespace App\Observers;

use App\Models\Contribution;
use App\Services\LedgerService;

class ContributionObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the Contribution "updated" event.
     */
    public function updated(Contribution $contribution): void
    {
        if ($contribution->wasChanged('status') && $contribution->status === 'success' && !$contribution->ledger_journal_id) {
            $this->recordToLedger($contribution);
        }
    }

    /**
     * Handle the Contribution "created" event.
     */
    public function created(Contribution $contribution): void
    {
        if ($contribution->status === 'success' && !$contribution->ledger_journal_id) {
            $this->recordToLedger($contribution);
        }
    }

    protected function recordToLedger(Contribution $contribution): void
    {
        try {
            if ($contribution->category === 'loan_repayment') {
                return; // QardHasanRepayment records its own ledger entry
            }

            $journal = $contribution->category === 'fine'
                ? $this->ledgerService->recordFine($contribution)
                : $this->ledgerService->recordContribution($contribution);

            $contribution->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Exception $e) {
            \Log::error("Failed to record contribution in ledger: " . $e->getMessage());
        }
    }
}
