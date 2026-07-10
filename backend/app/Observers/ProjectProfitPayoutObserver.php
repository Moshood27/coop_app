<?php

namespace App\Observers;

use App\Models\ProjectProfitPayout;
use App\Services\LedgerService;

class ProjectProfitPayoutObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the ProjectProfitPayout "updated" event.
     */
    public function updated(ProjectProfitPayout $payout): void
    {
        if ($payout->wasChanged('status') && $payout->status === 'paid' && !$payout->ledger_journal_id) {
            $this->recordToLedger($payout);
        }
    }

    /**
     * Handle the ProjectProfitPayout "created" event.
     */
    public function created(ProjectProfitPayout $payout): void
    {
        if ($payout->status === 'paid' && !$payout->ledger_journal_id) {
            $this->recordToLedger($payout);
        }
    }

    protected function recordToLedger(ProjectProfitPayout $payout): void
    {
        try {
            $journal = $this->ledgerService->recordProjectProfitPayout($payout);
            $payout->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Exception $e) {
            \Log::error("Failed to record project profit payout in ledger: " . $e->getMessage());
        }
    }
}
