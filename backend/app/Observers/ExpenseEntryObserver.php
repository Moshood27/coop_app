<?php

namespace App\Observers;

use App\Models\ExpenseEntry;
use App\Services\LedgerService;

class ExpenseEntryObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the ExpenseEntry "updated" event.
     */
    public function updated(ExpenseEntry $expenseEntry): void
    {
        if ($expenseEntry->wasChanged('status') && $expenseEntry->status === 'processed' && !$expenseEntry->ledger_journal_id) {
            $this->recordToLedger($expenseEntry);
        }
    }

    /**
     * Handle the ExpenseEntry "created" event.
     */
    public function created(ExpenseEntry $expenseEntry): void
    {
        if ($expenseEntry->status === 'processed' && !$expenseEntry->ledger_journal_id) {
            $this->recordToLedger($expenseEntry);
        }
    }

    protected function recordToLedger(ExpenseEntry $expenseEntry): void
    {
        try {
            $journal = $this->ledgerService->recordExpense($expenseEntry);
            $expenseEntry->updateQuietly(['ledger_journal_id' => $journal->id]);
        } catch (\Exception $e) {
            \Log::error("Failed to record expense in ledger: " . $e->getMessage());
        }
    }
}
