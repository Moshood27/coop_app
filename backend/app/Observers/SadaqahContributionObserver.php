<?php

namespace App\Observers;

use App\Models\SadaqahContribution;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Log;

class SadaqahContributionObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    public function created(SadaqahContribution $contribution): void
    {
        if ($contribution->status === 'success' && !$contribution->ledger_journal_id) {
            $this->recordToLedger($contribution);
        }
    }

    public function updated(SadaqahContribution $contribution): void
    {
        if ($contribution->status === 'success' && $contribution->wasChanged('status') && !$contribution->ledger_journal_id) {
            $this->recordToLedger($contribution);
        }
    }

    protected function recordToLedger(SadaqahContribution $contribution): void
    {
        try {
            $journal = $this->ledgerService->recordSadaqahContribution($contribution);
            $contribution->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Throwable $e) {
            Log::error("Failed to record sadaqah contribution in ledger: " . $e->getMessage());
        }
    }
}
