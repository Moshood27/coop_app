<?php

namespace App\Observers;

use App\Models\TakafulContribution;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Log;

class TakafulContributionObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    public function created(TakafulContribution $contribution): void
    {
        if ($contribution->status === 'success' && !$contribution->ledger_journal_id) {
            $this->recordToLedger($contribution);
        }
    }

    public function updated(TakafulContribution $contribution): void
    {
        if ($contribution->status === 'success' && $contribution->wasChanged('status') && !$contribution->ledger_journal_id) {
            $this->recordToLedger($contribution);
        }
    }

    protected function recordToLedger(TakafulContribution $contribution): void
    {
        try {
            $journal = $this->ledgerService->recordTakafulContribution($contribution);
            $contribution->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Throwable $e) {
            Log::error("Failed to record takaful contribution in ledger: " . $e->getMessage());
        }
    }
}
