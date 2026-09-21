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
    public function run(bool $fix = false, $userId = null, $branchId = null)
    {
        return [
            'wallet' => $this->reconcileWallet($fix, $userId, $branchId),
            'contributions' => $this->reconcileContributions($fix, $userId, $branchId),
            'loans' => $this->reconcileLoans($fix, $userId, $branchId),
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    protected function reconcileWallet(bool $fix, $userId, $branchId = null)
    {
        $report = ['missing_ledger' => 0, 'balance_mismatches' => 0, 'fixed' => 0, 'errors' => []];

        // A. Missing Ledger Journals for Wallet Transactions
        $query = WalletTransaction::whereNull('ledger_journal_id');
        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($branchId) {
            $query->whereHas('user', fn($q) => $q->where('branch_id', $branchId));
        }

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
        if ($userId) {
            $userQuery->where('id', $userId);
        } elseif ($branchId) {
            $userQuery->where('branch_id', $branchId);
        } else {
            $userQuery->where('is_admin', false);
        }

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

    protected function reconcileContributions(bool $fix, $userId, $branchId = null)
    {
        $report = ['missing_ledger' => 0, 'scheme_mismatches' => 0, 'fixed' => 0, 'errors' => []];

        // A. Missing Ledger Journals for Contributions
        $query = Contribution::where('status', 'success')->whereNull('ledger_journal_id');
        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($branchId) {
            $query->whereHas('user', fn($q) => $q->where('branch_id', $branchId));
        }

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
        if ($userId) {
            $userQuery->where('id', $userId);
        } elseif ($branchId) {
            $userQuery->where('branch_id', $branchId);
        } else {
            $userQuery->where('is_admin', false);
        }

        $userQuery->chunk(100, function ($users) use (&$report, $fix) {
            $columnToSchemes = [
                'ordinary_savings' => ['Savings', 'Ordinary Savings'],
                'shares_capital' => ['Shares', 'Share Capital'],
                'special_savings_balance' => ['Special Savings'],
                'building_balance' => ['Building'],
                'development_fund_balance' => ['Development'],
                'agm_balance' => ['AGM'],
                'loan_repayment_balance' => ['Loan Repayment'],
                'fine_balance' => ['Fine'],
                'welfare_balance' => ['Welfare'],
                'lateness_balance' => ['Lateness'],
                'stationery_balance' => ['Stationery'],
                'loan_form_balance' => ['Loan Form'],
                'others_balance' => ['Others'],
                'id_card_balance' => ['ID Card'],
                'emergency_balance' => ['Emergency'],
                'entrance_balance' => ['Entrance'],
                'h_savings_balance' => ['H Savings'],
                'investment_balance' => ['Investment'],
                'group_savings_balance' => ['Group Savings'],
            ];

            foreach ($users as $user) {
                foreach ($columnToSchemes as $column => $schemeNames) {
                    $actual = (float) $user->contributions()
                        ->whereHas('scheme', fn($q) => $q->whereIn('name', $schemeNames))
                        ->where('status', 'success')
                        ->sum('amount');
                    $stored = (float) $user->{$column};

                    if (abs($actual - $stored) > 0.01) {
                        $report['scheme_mismatches']++;
                        if ($fix) {
                            $user->{$column} = $actual;
                            $user->saveQuietly();
                            $report['fixed']++;
                        }
                    }
                }
            }
        });

        return $report;
    }

    protected function reconcileLoans(bool $fix, $userId, $branchId = null)
    {
        $report = ['missing_ledger' => 0, 'missing_passbook' => 0, 'balance_mismatches' => 0, 'fixed' => 0, 'errors' => []];

        // A. Missing Ledger Journals for Loan Repayments
        $repQuery = QardHasanRepayment::where('status', 'success')->whereNull('ledger_journal_id');
        if ($userId) {
            $repQuery->whereHas('qardHasan', fn($q) => $q->where('user_id', $userId));
        } elseif ($branchId) {
            $repQuery->whereHas('qardHasan.user', fn($q) => $q->where('branch_id', $branchId));
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
        if ($userId) {
            $repQuery2->whereHas('qardHasan', fn($q) => $q->where('user_id', $userId));
        } elseif ($branchId) {
            $repQuery2->whereHas('qardHasan.user', fn($q) => $q->where('branch_id', $branchId));
        }

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
        if ($userId) {
            $loanQuery->where('user_id', $userId);
        } elseif ($branchId) {
            $loanQuery->whereHas('user', fn($q) => $q->where('branch_id', $branchId));
        }

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

    /**
     * Rollback records created by the automatic sync command.
     */
    public function rollbackSync(bool $fix = false, $branchId = null)
    {
        $report = ['repayments_deleted' => 0, 'contributions_deleted' => 0, 'loans_updated' => 0];

        // 1. Identify synced repayments (Part 1 of Sync Command)
        $repQuery = QardHasanRepayment::where('notes', 'Repayment via Wallet (Synced)');
        if ($branchId) {
            $repQuery->whereHas('qardHasan.user', fn($q) => $q->where('branch_id', $branchId));
        }

        $repayments = $repQuery->get();
        $report['repayments_deleted'] = $repayments->count();

        if ($fix) {
            $affectedLoans = [];
            foreach ($repayments as $r) {
                $loan = $r->qardHasan;
                $r->delete();
                if ($loan && !isset($affectedLoans[$loan->id])) {
                    $affectedLoans[$loan->id] = $loan;
                }
            }

            foreach ($affectedLoans as $loan) {
                $loan->paid_amount = (float) $loan->repayments()->where('status', 'success')->sum('amount');
                if ($loan->paid_amount < $loan->principal_amount && $loan->status === 'completed') {
                    $loan->status = 'active';
                }
                $loan->saveQuietly();
                $report['loans_updated']++;
            }
        }

        // 2. Identify synced contributions (Part 2 of Sync Command)
        $conQuery = Contribution::where('notes', 'like', 'Repayment for Loan QH-% (Synced)');
        if ($branchId) {
            $conQuery->whereHas('user', fn($q) => $q->where('branch_id', $branchId));
        }

        $contributions = $conQuery->get();
        $report['contributions_deleted'] = $contributions->count();

        if ($fix) {
            foreach ($contributions as $c) {
                $c->delete();
            }
        }

        return $report;
    }
}
