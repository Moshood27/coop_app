<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\LedgerJournal;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use App\Models\Scheme;

class FinancialReconciliationService
{
    protected $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Run the full financial reconciliation process.
     */
    public function run(bool $fix = false, $userId = null)
    {
        return [
            'wallet' => $this->reconcileWallet($fix, $userId),
            'contributions' => $this->reconcileContributions($fix, $userId),
            'loans' => $this->reconcileLoans($fix, $userId),
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    protected function reconcileWallet(bool $fix, $userId)
    {
        $report = ['missing_ledger' => 0, 'balance_mismatches' => 0, 'fixed' => 0, 'errors' => []];

        // A. Missing Ledger Journals for Wallet Transactions
        $query = WalletTransaction::whereNull('ledger_journal_id');
        if ($userId) $query->where('user_id', $userId);

        $missing = $query->get();
        $report['missing_ledger'] = $missing->count();

        if ($fix && $report['missing_ledger'] > 0) {
            foreach ($missing as $tx) {
                try {
                    $isCredit = strtolower((string) $tx->type) === 'credit';
                    $journal = $isCredit
                        ? $this->ledgerService->recordWalletCredit($tx)
                        : $this->ledgerService->recordWalletDebit($tx);
                    $tx->updateQuietly(['ledger_journal_id' => $journal->id]);
                    $report['fixed']++;
                } catch (\Exception $e) {
                    $report['errors'][] = "Transaction #{$tx->id}: " . $e->getMessage();
                }
            }
        }

        // B. User Wallet Balance Consistency
        $userQuery = User::query();
        if ($userId) $userQuery->where('id', $userId);
        else $userQuery->where('is_admin', false);

        $userQuery->chunk(100, function ($users) use (&$report, $fix) {
            foreach ($users as $user) {
                $credits = (float) $user->walletTransactions()->where('type', 'credit')->sum('amount');
                $debits = (float) $user->walletTransactions()->where('type', 'debit')->sum('amount');
                $calculated = round($credits - $debits, 2);
                $stored = (float) $user->balance;

                if (abs($calculated - $stored) > 0.01) {
                    $report['balance_mismatches']++;
                    if ($fix) {
                        $user->balance = $calculated;
                        $user->saveQuietly();
                        $report['fixed']++;
                    }
                }
            }
        });

        return $report;
    }

    protected function reconcileContributions(bool $fix, $userId)
    {
        $report = ['missing_ledger' => 0, 'scheme_mismatches' => 0, 'fixed' => 0, 'errors' => []];

        // A. Missing Ledger Journals for Contributions
        $query = Contribution::where('status', 'success')->whereNull('ledger_journal_id');
        if ($userId) $query->where('user_id', $userId);

        $missing = $query->get();
        $report['missing_ledger'] = $missing->count();

        if ($fix && $report['missing_ledger'] > 0) {
            foreach ($missing as $c) {
                try {
                    if ($c->category === 'loan_repayment') {
                        $repayment = QardHasanRepayment::where('reference', $c->reference)->first();
                        if ($repayment && $repayment->ledger_journal_id) {
                            $c->updateQuietly(['ledger_journal_id' => $repayment->ledger_journal_id]);
                            $report['fixed']++;
                            continue;
                        }
                    }
                    $journal = $c->category === 'fine'
                        ? $this->ledgerService->recordFine($c)
                        : $this->ledgerService->recordContribution($c);
                    $c->updateQuietly(['ledger_journal_id' => $journal->id]);
                    $report['fixed']++;
                } catch (\Exception $e) {
                    $report['errors'][] = "Contribution #{$c->id}: " . $e->getMessage();
                }
            }
        }

        // B. Scheme Balance Consistency (Savings, Shares, etc.)
        $userQuery = User::query();
        if ($userId) $userQuery->where('id', $userId);
        else $userQuery->where('is_admin', false);

        $userQuery->chunk(100, function ($users) use (&$report, $fix) {
            $columnMap = [
                'Savings' => 'ordinary_savings',
                'Ordinary Savings' => 'ordinary_savings',
                'Shares' => 'shares_capital',
                'Share Capital' => 'shares_capital',
                'Special Savings' => 'special_savings_balance',
            ];

            foreach ($users as $user) {
                foreach ($columnMap as $schemeName => $column) {
                    $actual = (float) $user->contributions()
                        ->whereHas('scheme', fn($q) => $q->where('name', $schemeName))
                        ->where('status', 'success')
                        ->sum('amount');
                    $stored = (float) $user->{$column};

                    if (abs($actual - $stored) > 0.01) {
                        $report['scheme_mismatches']++;
                        if ($fix) {
                            $user->syncSchemeBalance($schemeName);
                            $report['fixed']++;
                        }
                    }
                }
            }
        });

        return $report;
    }

    protected function reconcileLoans(bool $fix, $userId)
    {
        $report = ['missing_ledger' => 0, 'missing_passbook' => 0, 'balance_mismatches' => 0, 'fixed' => 0, 'errors' => []];

        // A. Missing Ledger Journals for Loan Repayments
        $repQuery = QardHasanRepayment::where('status', 'success')->whereNull('ledger_journal_id');
        if ($userId) {
            $repQuery->whereHas('qardHasan', fn($q) => $q->where('user_id', $userId));
        }
        $missing = $repQuery->get();
        $report['missing_ledger'] = $missing->count();

        if ($fix && $report['missing_ledger'] > 0) {
            foreach ($missing as $r) {
                try {
                    $journal = $this->ledgerService->recordLoanRepayment($r);
                    $r->updateQuietly(['ledger_journal_id' => $journal->id]);
                    $report['fixed']++;
                } catch (\Exception $e) {
                    $report['errors'][] = "Repayment #{$r->id}: " . $e->getMessage();
                }
            }
        }

        // B. Repayment vs Passbook (Contribution) linkage
        $repQuery2 = QardHasanRepayment::where('status', 'success');
        if ($userId) $repQuery2->whereHas('qardHasan', fn($q) => $q->where('user_id', $userId));

        $repQuery2->chunk(100, function ($repayments) use (&$report, $fix) {
            foreach ($repayments as $r) {
                $exists = Contribution::where('reference', $r->reference)->exists();
                if (!$exists) {
                    $report['missing_passbook']++;
                    if ($fix) {
                        try {
                            app(\App\Observers\QardHasanRepaymentObserver::class)->created($r);
                            $report['fixed']++;
                        } catch (\Exception $e) {
                            $report['errors'][] = "Passbook link for Repayment #{$r->id}: " . $e->getMessage();
                        }
                    }
                }
            }
        });

        // C. Loan Paid Amount vs Repayment Total
        $loanQuery = QardHasan::whereIn('status', ['active', 'defaulted', 'completed']);
        if ($userId) $loanQuery->where('user_id', $userId);

        $loanQuery->chunk(100, function ($loans) use (&$report, $fix) {
            foreach ($loans as $loan) {
                $calculated = (float) $loan->repayments()->where('status', 'success')->sum('amount');
                $stored = (float) $loan->paid_amount;

                if (abs($calculated - $stored) > 0.01) {
                    $report['balance_mismatches']++;
                    if ($fix) {
                        $loan->paid_amount = $calculated;
                        if ($calculated >= $loan->principal_amount) {
                            $loan->status = 'completed';
                        }
                        $loan->saveQuietly();
                        $report['fixed']++;
                    }
                }
            }
        });

        return $report;
    }
}
