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
        \Log::info("ContributionObserver: updated event for contribution #{$contribution->id}, status: {$contribution->status}, wasChanged: " . ($contribution->wasChanged('status') ? 'yes' : 'no'));
        if ($contribution->wasChanged('status') && $contribution->status === 'success' && !$contribution->ledger_journal_id) {
            $this->recordToLedger($contribution);
        }
    }

    /**
     * Handle the Contribution "created" event.
     */
    public function created(Contribution $contribution): void
    {
        \Log::info("ContributionObserver: created event for contribution #{$contribution->id}, status: {$contribution->status}");
        if ($contribution->status === 'success' && !$contribution->ledger_journal_id) {
            $this->recordToLedger($contribution);
        }
    }

    protected function recordToLedger(Contribution $contribution): void
    {
        try {
            if ($contribution->category === 'loan_repayment') {
                \Log::info("ContributionObserver: Skipping loan_repayment (Contribution #{$contribution->id}), will be handled by QardHasanRepaymentObserver");
                return; // QardHasanRepayment records its own ledger entry
            }

            \Log::info("ContributionObserver: Recording to ledger for contribution #{$contribution->id}");
            $journal = $contribution->category === 'fine'
                ? $this->ledgerService->recordFine($contribution)
                : $this->ledgerService->recordContribution($contribution);

            $contribution->updateQuietly(['ledger_journal_id' => $journal->id]);
            \Log::info("ContributionObserver: Successfully recorded ledger journal #{$journal->id} for contribution #{$contribution->id}");
        } catch (\Exception $e) {
            \Log::error("Failed to record contribution in ledger: " . $e->getMessage());
        }
    }
}
