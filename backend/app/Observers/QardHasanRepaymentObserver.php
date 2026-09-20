<?php

namespace App\Observers;

use App\Models\QardHasanRepayment;
use App\Models\Contribution;
use App\Models\Scheme;
use App\Services\LedgerService;

class QardHasanRepaymentObserver
{
    public function __construct(protected LedgerService $ledgerService)
    {}

    /**
     * Handle the QardHasanRepayment "updated" event.
     */
    public function updated(QardHasanRepayment $repayment): void
    {
        \Log::info("QardHasanRepaymentObserver: updated event for repayment #{$repayment->id}, status: {$repayment->status}");
        if ($repayment->wasChanged('status') && $repayment->status === 'success') {
            $this->syncWithContribution($repayment);
            if (!$repayment->ledger_journal_id) {
                $this->recordToLedger($repayment);
            }
        }
    }

    /**
     * Handle the QardHasanRepayment "created" event.
     */
    public function created(QardHasanRepayment $repayment): void
    {
        \Log::info("QardHasanRepaymentObserver: created event for repayment #{$repayment->id}, status: {$repayment->status}");
        if ($repayment->status === 'success') {
            $this->syncWithContribution($repayment);
            if (!$repayment->ledger_journal_id) {
                $this->recordToLedger($repayment);
            }
        }
    }

    protected function syncWithContribution(QardHasanRepayment $repayment): void
    {
        try {
            $loan = $repayment->qardHasan;
            if (!$loan) return;

            $contribution = Contribution::where('reference', $repayment->reference)->first();

            if (!$contribution) {
                $scheme = Scheme::where('name', 'Loan Repayment')->first();
                if ($scheme) {
                    Contribution::create([
                        'user_id' => $loan->user_id,
                        'scheme_id' => $scheme->id,
                        'amount' => $repayment->amount,
                        'status' => 'success',
                        'paid_at' => $repayment->paid_at ?? $repayment->created_at,
                        'payment_method' => $repayment->payment_method,
                        'reference' => $repayment->reference,
                        'category' => 'loan_repayment',
                        'qard_hasan_id' => $loan->id,
                        'notes' => $repayment->notes ?? "Repayment for Loan QH-{$loan->id}",
                    ]);
                    \Log::info("QardHasanRepaymentObserver: Created contribution for repayment #{$repayment->id}");
                }
            } else {
                // Ensure existing contribution is linked to the loan
                if (!$contribution->qard_hasan_id || $contribution->category !== 'loan_repayment') {
                    $contribution->updateQuietly([
                        'qard_hasan_id' => $loan->id,
                        'category' => 'loan_repayment'
                    ]);
                    \Log::info("QardHasanRepaymentObserver: Linked existing contribution #{$contribution->id} to loan #{$loan->id}");
                }
            }
        } catch (\Exception $e) {
            \Log::error("Failed to sync repayment with contribution: " . $e->getMessage());
        }
    }

    protected function recordToLedger(QardHasanRepayment $repayment): void
    {
        try {
            \Log::info("QardHasanRepaymentObserver: Recording to ledger for repayment #{$repayment->id}");
            $journal = $this->ledgerService->recordLoanRepayment($repayment);
            $repayment->updateQuietly(['ledger_journal_id' => $journal->id]);
            \Log::info("QardHasanRepaymentObserver: Successfully recorded ledger journal #{$journal->id} for repayment #{$repayment->id}");
        } catch (\Exception $e) {
            \Log::error("Failed to record loan repayment in ledger: " . $e->getMessage());
        }
    }
}
