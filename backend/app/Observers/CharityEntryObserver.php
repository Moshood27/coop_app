<?php

namespace App\Observers;

use App\Models\CharityEntry;
use App\Services\LedgerService;

class CharityEntryObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the CharityEntry "updated" event.
     */
    public function updated(CharityEntry $charityEntry): void
    {
        if ($charityEntry->wasChanged('status') && $charityEntry->status === 'processed' && !$charityEntry->ledger_journal_id) {
            $this->recordToLedger($charityEntry);
        }
    }

    /**
     * Handle the CharityEntry "created" event.
     */
    public function created(CharityEntry $charityEntry): void
    {
        if ($charityEntry->status === 'processed' && !$charityEntry->ledger_journal_id) {
            $this->recordToLedger($charityEntry);
        }
    }

    protected function recordToLedger(CharityEntry $charityEntry): void
    {
        try {
            $journal = $this->ledgerService->recordCharityReceipt($charityEntry);
            $charityEntry->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Exception $e) {
            \Log::error("Failed to record charity in ledger: " . $e->getMessage());
        }
    }
}
