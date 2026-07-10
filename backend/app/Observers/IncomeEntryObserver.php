<?php

namespace App\Observers;

use App\Models\IncomeEntry;
use App\Services\LedgerService;

class IncomeEntryObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the IncomeEntry "created" event.
     */
    public function created(IncomeEntry $incomeEntry): void
    {
        try {
            if (!$incomeEntry->ledger_journal_id) {
                $journal = $this->ledgerService->recordIncome($incomeEntry);
                $incomeEntry->updateQuietly(['ledger_journal_id' => $journal->id]);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to record income in ledger: " . $e->getMessage());
        }
    }
}
