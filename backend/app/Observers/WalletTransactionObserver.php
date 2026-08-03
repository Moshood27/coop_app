<?php

namespace App\Observers;

use App\Models\WalletTransaction;
use App\Services\LedgerService;

class WalletTransactionObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the WalletTransaction "created" event.
     */
    public function created(WalletTransaction $tx): void
    {
        try {
            $isCredit = strtolower((string) $tx->type) === 'credit';

            if (!$tx->ledger_journal_id) {
                $journal = $isCredit
                    ? $this->ledgerService->recordWalletCredit($tx)
                    : $this->ledgerService->recordWalletDebit($tx);

                $tx->updateQuietly(['ledger_journal_id' => $journal->id]);
            }

            // Auto-process pending administrative charges if it was a credit (wallet funding)
            if ($isCredit && $tx->user) {
                app(\App\Services\AdministrativeChargeService::class)->attemptDeduction($tx->user);

                // Auto-recover overdue loans if enabled
                \App\Jobs\AutoRecoverOverdueLoans::dispatch($tx->user_id)->delay(now()->addSeconds(5));
            }
        } catch (\Exception $e) {
            \Log::error("Failed to record wallet transaction in ledger: " . $e->getMessage());
        }
    }
}
