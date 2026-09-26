<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Scheme;
use App\Models\WalletTransaction;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\CharityEntry;
use App\Models\IncomeEntry;
use App\Models\ExpenseEntry;
use App\Models\StoreOrder;
use App\Models\ProjectProfit;
use App\Models\User;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class BranchReportService
{
    public function buildBranchQardHasanReport(?int $branchId = null, ?string $from = null, ?string $to = null, bool $onlyDefaulted = false): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $branches = \App\Models\Branch::when($branchId, fn($q) => $q->where('id', $branchId))
            ->with(['users.qardHasans' => function ($query) use ($fromDate, $toDate, $onlyDefaulted) {
                $query->whereIn('status', ['active', 'defaulted']);

                if ($onlyDefaulted) {
                    $query->whereNotNull('defaulted_at')
                        ->where('defaulted_at', '<=', now());
                }

                if ($fromDate) {
                    $query->where('created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $query->where('created_at', '<=', $toDate);
                }
            }, 'users.qardHasans.repayments' => function($q) {
                $q->whereIn('status', ['success', 'paid', 'completed'])->orderBy('paid_at', 'desc');
            }])->get();

        $report = [
            'branches' => [],
            'grand_total_principal' => 0,
            'grand_total_paid' => 0,
            'grand_total_overdue' => 0,
            'grand_total_outstanding' => 0,
            'grand_total_loans_count' => 0,
            'from' => $from,
            'to' => $to,
        ];

        foreach ($branches as $branch) {
            $branchData = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'loans' => [],
                'total_principal' => 0,
                'total_paid' => 0,
                'total_overdue' => 0,
                'total_outstanding' => 0,
            ];

            foreach ($branch->users as $user) {
                foreach ($user->qardHasans as $loan) {
                    $outstanding = max(0, (float)$loan->principal_amount - (float)$loan->paid_amount);
                    if ($outstanding > 0) {
                        $lastPayment = $loan->repayments->first();
                        $overdue = (float)$loan->getOverdueAmount();

                        $branchData['loans'][] = [
                            'member_name' => $user->full_name,
                            'loan_id' => $loan->qard_id_string,
                            'principal' => (float)$loan->principal_amount,
                            'paid' => (float)$loan->paid_amount,
                            'outstanding' => $outstanding,
                            'overdue' => $overdue,
                            'status' => ($loan->defaulted_at && $loan->defaulted_at->year > 1970 && $loan->defaulted_at->lte(now())) ? 'DEFAULTED' : $loan->status,
                            'last_payment_date' => $lastPayment ? $lastPayment->paid_at : null,
                        ];
                        $branchData['total_principal'] += (float)$loan->principal_amount;
                        $branchData['total_paid'] += (float)$loan->paid_amount;
                        $branchData['total_overdue'] += $overdue;
                        $branchData['total_outstanding'] += $outstanding;
                    }
                }
            }

            if (!empty($branchData['loans'])) {
                $report['branches'][] = $branchData;
                $report['grand_total_principal'] += $branchData['total_principal'];
                $report['grand_total_paid'] += $branchData['total_paid'];
                $report['grand_total_overdue'] += $branchData['total_overdue'];
                $report['grand_total_outstanding'] += $branchData['total_outstanding'];
                $report['grand_total_loans_count'] += count($branchData['loans']);
            }
        }

        return $report;
    }

    public function buildBranchContributionReport(?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $branches = \App\Models\Branch::when($branchId, fn($q) => $q->where('id', $branchId))
            ->with(['users.contributions' => function ($query) use ($fromDate, $toDate) {
                $query->whereIn('status', ['success', 'paid', 'completed']);
                if ($fromDate) {
                    $query->where('created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $query->where('created_at', '<=', $toDate);
                }
            }])->get();

        $report = [
            'branches' => [],
            'grand_total_amount' => 0,
            'grand_total_members_count' => 0,
            'from' => $from,
            'to' => $to,
        ];

        foreach ($branches as $branch) {
            $branchData = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'members' => [],
                'total_amount' => 0,
            ];

            foreach ($branch->users as $user) {
                $userTotal = $user->contributions->sum('amount');

                if ($userTotal > 0) {
                    $branchData['members'][] = [
                        'member_name' => $user->full_name,
                        'membership_number' => $user->membership_number,
                        'total_contributed' => (float)$userTotal,
                        'last_contribution_date' => $user->contributions->max(fn($c) => $c->paid_at ?? $c->created_at),
                    ];
                    $branchData['total_amount'] += (float)$userTotal;
                }
            }

            if (!empty($branchData['members'])) {
                // Sort members by amount descending
                usort($branchData['members'], fn($a, $b) => $b['total_contributed'] <=> $a['total_contributed']);

                $report['branches'][] = $branchData;
                $report['grand_total_amount'] += $branchData['total_amount'];
                $report['grand_total_members_count'] += count($branchData['members']);
            }
        }

        return $report;
    }

    public function buildBranchWalletTransactionsReport(?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $branches = \App\Models\Branch::when($branchId, fn($q) => $q->where('id', $branchId))
            ->with(['users.walletTransactions' => function ($query) use ($fromDate, $toDate) {
                if ($fromDate) {
                    $query->where('created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $query->where('created_at', '<=', $toDate);
                }
                $query->latest();
            }])->get();

        $report = [
            'branches' => [],
            'grand_total_credits' => 0,
            'grand_total_debits' => 0,
            'grand_total_net' => 0,
            'grand_total_members_count' => 0,
            'from' => $from,
            'to' => $to,
        ];

        foreach ($branches as $branch) {
            $branchData = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'members' => [],
                'total_credits' => 0,
                'total_debits' => 0,
                'total_net' => 0,
            ];

            foreach ($branch->users as $user) {
                $credits = $user->walletTransactions->where('type', 'credit')->sum('amount');
                $debits = $user->walletTransactions->where('type', 'debit')->sum('amount');

                if ($user->walletTransactions->count() > 0) {
                    $branchData['members'][] = [
                        'member_name' => $user->full_name,
                        'membership_number' => $user->membership_number,
                        'credits' => (float)$credits,
                        'debits' => (float)$debits,
                        'net' => (float)($credits - $debits),
                        'transaction_count' => $user->walletTransactions->count(),
                        'last_transaction_date' => $user->walletTransactions->max('created_at'),
                    ];
                    $branchData['total_credits'] += (float)$credits;
                    $branchData['total_debits'] += (float)$debits;
                }
            }

            if (!empty($branchData['members'])) {
                $branchData['total_net'] = $branchData['total_credits'] - $branchData['total_debits'];
                // Sort members by net descending
                usort($branchData['members'], fn($a, $b) => $b['net'] <=> $a['net']);

                $report['branches'][] = $branchData;
                $report['grand_total_credits'] += $branchData['total_credits'];
                $report['grand_total_debits'] += $branchData['total_debits'];
                $report['grand_total_members_count'] += count($branchData['members']);
            }
        }

        $report['grand_total_net'] = $report['grand_total_credits'] - $report['grand_total_debits'];

        return $report;
    }

    public function buildBranchMemberBalancesReport(?int $branchId = null, float $goldPrice = 0.0): array
    {
        $branches = \App\Models\Branch::when($branchId, fn($q) => $q->where('id', $branchId))
            ->with(['users'])
            ->get();

        $report = [
            'branches' => [],
            'grand_total_savings' => 0,
            'grand_total_special_savings' => 0,
            'grand_total_shares' => 0,
            'grand_total_gold_weight' => 0,
            'grand_total_gold_value' => 0,
            'grand_total_other' => 0,
            'grand_total_members_count' => 0,
        ];

        foreach ($branches as $branch) {
            $branchData = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'members' => [],
                'total_savings' => 0,
                'total_special_savings' => 0,
                'total_shares' => 0,
                'total_gold_weight' => 0,
                'total_gold_value' => 0,
                'total_other' => 0,
            ];

            foreach ($branch->users as $user) {
                $otherTotal = (float)$user->building_balance +
                              (float)$user->development_fund_balance +
                              (float)$user->agm_balance +
                              (float)$user->loan_repayment_balance +
                              (float)$user->fine_balance +
                              (float)$user->welfare_balance +
                              (float)$user->lateness_balance +
                              (float)$user->stationery_balance +
                              (float)$user->loan_form_balance +
                              (float)$user->others_balance +
                              (float)$user->id_card_balance +
                              (float)$user->emergency_balance +
                              (float)$user->entrance_balance +
                              (float)$user->h_savings_balance +
                              (float)$user->investment_balance +
                              (float)$user->group_savings_balance;

                $hasBalance = (float)$user->ordinary_savings > 0 ||
                              (float)$user->special_savings_balance > 0 ||
                              (float)$user->shares_capital > 0 ||
                              (float)$user->gold_balance > 0 ||
                              $otherTotal > 0;

                if ($hasBalance) {
                    $goldValue = (float)$user->gold_balance * $goldPrice;
                    $branchData['members'][] = [
                        'member_name' => $user->full_name,
                        'membership_number' => $user->membership_number,
                        'savings' => (float)$user->ordinary_savings,
                        'special_savings' => (float)$user->special_savings_balance,
                        'shares' => (float)$user->shares_capital,
                        'gold_weight' => (float)$user->gold_balance,
                        'gold_value' => $goldValue,
                        'other_funds' => $otherTotal,
                        'total_wealth' => (float)$user->ordinary_savings + (float)$user->special_savings_balance + (float)$user->shares_capital + $goldValue + $otherTotal,
                    ];
                    $branchData['total_savings'] += (float)$user->ordinary_savings;
                    $branchData['total_special_savings'] += (float)$user->special_savings_balance;
                    $branchData['total_shares'] += (float)$user->shares_capital;
                    $branchData['total_gold_weight'] += (float)$user->gold_balance;
                    $branchData['total_gold_value'] += $goldValue;
                    $branchData['total_other'] += $otherTotal;
                }
            }

            if (!empty($branchData['members'])) {
                // Sort by total wealth descending
                usort($branchData['members'], fn($a, $b) => $b['total_wealth'] <=> $a['total_wealth']);

                $report['branches'][] = $branchData;
                $report['grand_total_savings'] += $branchData['total_savings'];
                $report['grand_total_special_savings'] += $branchData['total_special_savings'];
                $report['grand_total_shares'] += $branchData['total_shares'];
                $report['grand_total_gold_weight'] += $branchData['total_gold_weight'];
                $report['grand_total_gold_value'] += $branchData['total_gold_value'];
                $report['grand_total_other'] += $branchData['total_other'];
                $report['grand_total_members_count'] += count($branchData['members']);
            }
        }

        return $report;
    }

    public function buildUsersByBranchReport(?int $branchId = null): array
    {
        $branches = \App\Models\Branch::when($branchId, fn($q) => $q->where('id', $branchId))
            ->with(['users'])
            ->get();

        $report = [
            'branches' => [],
            'grand_total_members_count' => 0,
        ];

        foreach ($branches as $branch) {
            $branchData = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'members' => [],
            ];

            foreach ($branch->users as $user) {
                $branchData['members'][] = [
                    'member_name' => $user->full_name,
                    'membership_number' => $user->membership_number,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->approval_status,
                    'joined_at' => $user->created_at?->format('Y-m-d'),
                ];
            }

            if (!empty($branchData['members'])) {
                // Sort by name
                usort($branchData['members'], fn($a, $b) => strcmp($a['member_name'], $b['member_name']));

                $report['branches'][] = $branchData;
                $report['grand_total_members_count'] += count($branchData['members']);
            }
        }

        return $report;
    }

    public function buildBranchSchemeReport(?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        // Include active schemes and any inactive schemes that have contributions in the period
        $periodSchemeIds = \App\Models\Contribution::whereIn('status', ['success', 'paid', 'completed'])
            ->when($fromDate, fn($q) => $q->where('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->where('created_at', '<=', $toDate))
            ->distinct()
            ->pluck('scheme_id');

        $schemes = \App\Models\Scheme::where('active', true)
            ->orWhereIn('id', $periodSchemeIds)
            ->get();

        $branches = \App\Models\Branch::when($branchId, fn($q) => $q->where('id', $branchId))
            ->with(['users.contributions' => function ($query) use ($fromDate, $toDate) {
                $query->whereIn('status', ['success', 'paid', 'completed']);
                if ($fromDate) {
                    $query->where('created_at', '>=', $fromDate);
                }
                if ($toDate) {
                    $query->where('created_at', '<=', $toDate);
                }
            }])->get();

        $report = [
            'branches' => [],
            'schemes' => $schemes->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->toArray(),
            'grand_totals' => [], // scheme_id => amount
            'grand_total_all' => 0,
            'grand_total_members_count' => 0,
            'from' => $from,
            'to' => $to,
        ];

        foreach ($schemes as $s) {
            $report['grand_totals'][$s->id] = 0;
        }

        foreach ($branches as $branch) {
            $branchData = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'members' => [],
                'totals' => [], // scheme_id => amount
                'branch_total' => 0,
            ];

            foreach ($schemes as $s) {
                $branchData['totals'][$s->id] = 0;
            }

            foreach ($branch->users as $user) {
                $memberSchemes = [];
                $memberTotal = 0;
                $hasContribution = false;

                $userContributions = $user->contributions->groupBy('scheme_id');

                foreach ($schemes as $s) {
                    $sum = $userContributions->has($s->id) ? $userContributions->get($s->id)->sum('amount') : 0;
                    $memberSchemes[$s->id] = (float)$sum;
                    $memberTotal += (float)$sum;

                    $branchData['totals'][$s->id] += (float)$sum;
                    $report['grand_totals'][$s->id] += (float)$sum;

                    if ($sum > 0) $hasContribution = true;
                }

                if ($hasContribution) {
                    $branchData['members'][] = [
                        'member_name' => $user->full_name,
                        'membership_number' => $user->membership_number,
                        'schemes' => $memberSchemes,
                        'total' => $memberTotal,
                    ];
                    $branchData['branch_total'] += $memberTotal;
                    $report['grand_total_all'] += $memberTotal;
                }
            }

            if (!empty($branchData['members'])) {
                // Sort members by total amount descending
                usort($branchData['members'], fn($a, $b) => $b['total'] <=> $a['total']);
                $report['branches'][] = $branchData;
                $report['grand_total_members_count'] += count($branchData['members']);
            }
        }

        return $report;
    }

    public function buildAdministrativeChargeReport(
        ?int $branchId = null,
        ?string $from = null,
        ?string $to = null,
        string $sortField = 'member_name',
        string $sortDirection = 'asc'
    ): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $branches = Branch::when($branchId, fn($q) => $q->where('id', $branchId))
            ->with(['users' => function ($q) {
                $q->whereNull('deceased_at');
            }])
            ->get();

        $report = [
            'branches' => [],
            'grand_total_collected' => 0,
            'grand_total_outstanding' => 0,
            'grand_total_members_count' => 0,
            'from' => $from,
            'to' => $to,
        ];

        foreach ($branches as $branch) {
            $branchData = [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'members' => [],
                'total_collected' => 0,
                'total_outstanding' => 0,
            ];

            foreach ($branch->users as $user) {
                // Get collected administrative charges for this member in the period via Contribution records
                $collected = (float) Contribution::where('user_id', $user->id)
                    ->where('status', 'success')
                    ->whereHas('scheme', function($q) {
                        $q->where('name', 'SITTING');
                    })
                    ->when($fromDate, fn($q) => $q->where('paid_at', '>=', $fromDate))
                    ->when($toDate, fn($q) => $q->where('paid_at', '<=', $toDate))
                    ->sum('amount');

                $outstanding = (float) $user->admin_charge_balance;

                if ($collected > 0 || $outstanding > 0) {
                    $branchData['members'][] = [
                        'member_name' => $user->full_name,
                        'membership_number' => $user->membership_number,
                        'is_distant' => (bool) $user->is_distant,
                        'collected' => $collected,
                        'outstanding' => $outstanding,
                        'last_charge_date' => $user->last_admin_charge_at ? $user->last_admin_charge_at->toDateTimeString() : null,
                        'last_charge_timestamp' => $user->last_admin_charge_at ? $user->last_admin_charge_at->timestamp : 0,
                    ];
                    $branchData['total_collected'] += $collected;
                    $branchData['total_outstanding'] += $outstanding;
                }
            }

            if (!empty($branchData['members'])) {
                // Sort members
                usort($branchData['members'], function ($a, $b) use ($sortField, $sortDirection) {
                    $valA = $a[$sortField] ?? '';
                    $valB = $b[$sortField] ?? '';

                    if ($sortField === 'last_charge_date') {
                        $valA = $a['last_charge_timestamp'];
                        $valB = $b['last_charge_timestamp'];
                    }

                    if ($sortDirection === 'asc') {
                        return $valA <=> $valB;
                    } else {
                        return $valB <=> $valA;
                    }
                });

                $report['branches'][] = $branchData;
                $report['grand_total_collected'] += $branchData['total_collected'];
                $report['grand_total_outstanding'] += $branchData['total_outstanding'];
                $report['grand_total_members_count'] += count($branchData['members']);
            }
        }

        return $report;
    }

}
