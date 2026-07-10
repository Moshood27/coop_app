<?php

namespace App\Observers;

use App\Models\ProjectProfit;
use App\Services\LedgerService;

class ProjectProfitObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the ProjectProfit "created" event.
     */
    public function created(ProjectProfit $profit): void
    {
        try {
            $journal = $this->ledgerService->recordProjectProfit($profit);
            $profit->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Exception $e) {
            \Log::error("Failed to record project profit in ledger: " . $e->getMessage());
        }
    }
}
