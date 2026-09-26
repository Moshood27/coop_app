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

class MemberReportService
{
    public function buildMemberSavingsLedger(int $userId): array
    {
        $user = User::findOrFail($userId);
        $contributions = \App\Models\Contribution::where('user_id', $userId)
            ->with('scheme')
            ->orderBy('created_at', 'desc')
            ->get();

        $takafulEntries = \App\Models\TakafulPoolEntry::where('user_id', $userId)
            ->where('direction', 'credit')
            ->orderBy('created_at', 'desc')
            ->get();

        $history = $contributions->map(fn($c) => [
            'date' => $c->created_at->toDateString(),
            'scheme' => $c->scheme?->name ?? 'Direct Contribution',
            'type' => $c->type,
            'amount' => (float)$c->amount,
            'status' => $c->status,
        ])->toArray();

        // Add Takaful entries to history
        foreach ($takafulEntries as $te) {
            $history[] = [
                'date' => $te->created_at->toDateString(),
                'scheme' => 'Takaful Welfare Pool',
                'type' => 'Contribution',
                'amount' => (float)$te->amount,
                'status' => 'success',
            ];
        }

        // Sort by date descending
        usort($history, fn($a, $b) => strcmp($b['date'], $a['date']));

        return [
            'member_name' => $user->full_name,
            'membership_number' => $user->membership_number,
            'current_savings' => (float)$user->ordinary_savings,
            'current_shares' => (float)$user->shares_capital,
            'current_gold' => (float)$user->gold_balance,
            'total_takaful_paid' => (float)$takafulEntries->sum('amount'),
            'history' => $history,
        ];
    }

    public function buildLoanAgingReport(): array
    {
        $now = Carbon::now();
        $agingData = [];

        // 1. Qard Hasan Loans
        $loans = QardHasan::whereIn('status', ['active', 'defaulted'])->with('user')->get();
        foreach ($loans as $l) {
            $lastRepayment = QardHasanRepayment::where('qard_hasan_id', $l->id)
                ->whereIn('status', ['success', 'paid', 'completed'])
                ->orderBy('paid_at', 'desc')
                ->first();

            $daysSinceLastPayment = $lastRepayment
                ? (int) abs($now->diffInDays(Carbon::parse($lastRepayment->paid_at)))
                : (int) abs($now->diffInDays($l->created_at));

            $repaid = QardHasanRepayment::where('qard_hasan_id', $l->id)
                ->whereIn('status', ['success', 'paid', 'completed'])
                ->sum('amount');

            $balance = (float)$l->principal_amount - (float)$repaid;

            $agingData[] = [
                'type' => 'Qard Hasan',
                'member' => $l->user->full_name,
                'principal' => (float)$l->principal_amount,
                'repaid' => (float)$repaid,
                'balance' => $balance,
                'days_since_last_payment' => DurationHelper::format($daysSinceLastPayment),
                'status' => $daysSinceLastPayment > 30 ? 'Overdue' : 'Active',
            ];
        }

        // 2. Murabahah Store Orders (Credit)
        $orders = StoreOrder::where('status', 'murabaha_active')->with('user')->get();
        foreach ($orders as $order) {
            $meta = $order->meta;
            $fin = $meta['financing'] ?? null;
            if (!is_array($fin) || ($fin['type'] ?? null) !== 'murabaha') continue;

            $schedule = $fin['schedule'] ?? [];
            $totalPaid = (float)($fin['total_paid'] ?? 0);
            $balance = (float)$order->total_amount - $totalPaid;

            // Find last payment date from schedule
            $lastPaidDate = null;
            foreach ($schedule as $item) {
                if (($item['status'] ?? '') === 'paid' && isset($item['paid_at'])) {
                    $pd = Carbon::parse($item['paid_at']);
                    if (!$lastPaidDate || $pd->gt($lastPaidDate)) {
                        $lastPaidDate = $pd;
                    }
                }
            }

            $daysSinceLastPayment = $lastPaidDate
                ? (int) abs($now->diffInDays($lastPaidDate))
                : (int) abs($now->diffInDays($order->created_at));

            $agingData[] = [
                'type' => 'Murabahah',
                'member' => $order->user->full_name,
                'principal' => (float)$order->total_amount,
                'repaid' => $totalPaid,
                'balance' => $balance,
                'days_since_last_payment' => DurationHelper::format($daysSinceLastPayment),
                'status' => $daysSinceLastPayment > 30 ? 'Overdue' : 'Active',
            ];
        }

        return $agingData;
    }

    public function buildMemberZakatPortfolio(?string $from = null, ?string $to = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfYear();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now();

        $zakatProject = \App\Models\SadaqahProject::where('name', 'General Zakat Fund')->first();
        if (!$zakatProject) return [];

        $contributions = \App\Models\SadaqahContribution::where('sadaqah_project_id', $zakatProject->id)
            ->where('status', 'success')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->with('user')
            ->get();

        $summary = $contributions->groupBy('user_id')->map(function ($group) {
            $user = $group->first()->user;
            return [
                'name' => $user?->full_name ?? 'Unknown',
                'membership_number' => $user?->membership_number ?? '-',
                'total_paid' => (float)$group->sum('amount'),
                'last_payment_date' => $group->max('created_at')->toDateString(),
                'count' => $group->count(),
            ];
        })->values()->sortByDesc('total_paid')->toArray();

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'total_zakat_collected' => (float)$contributions->sum('amount'),
            'members_count' => count($summary),
            'portfolio' => $summary,
        ];
    }

    public function buildLoanAnalysisReport(?int $branchId = null, ?string $dateStr = null, ?string $search = null): array
    {
        $toDate = $dateStr ? Carbon::parse($dateStr)->endOfMonth() : Carbon::now()->endOfMonth();
        return $this->generateLoanAnalysisData($toDate, $branchId, $search);
    }

    public function buildMemberLoanAnalysisReport(\App\Models\User $user, ?string $dateStr = null): array
    {
        $toDate = $dateStr ? Carbon::parse($dateStr)->endOfMonth() : Carbon::now()->endOfMonth();
        return $this->generateLoanAnalysisData($toDate, null, null, $user->id);
    }

    protected function generateLoanAnalysisData(Carbon $toDate, ?int $branchId = null, ?string $search = null, ?int $userId = null): array
    {
        $monthStr = $toDate->format('F');
        $yearStr = $toDate->format('Y');

        $savingsSchemes = \App\Models\Scheme::whereIn('name', ['Savings', 'Shares', 'Special Savings', 'Ordinary Savings', 'Share Capital'])->pluck('id')->toArray();

        // Get all active, defaulted or recently completed loans as of that date
        $loans = \App\Models\QardHasan::with(['user.branch', 'user.contributions' => function($q) use ($toDate, $savingsSchemes) {
            $q->where('status', 'success')
                ->where('created_at', '<=', $toDate)
                ->whereIn('scheme_id', $savingsSchemes);
        }])
            ->where('created_at', '<=', $toDate)
            ->when($branchId, function($q) use ($branchId) {
                $q->whereHas('user', fn($u) => $u->where('branch_id', $branchId));
            })
            ->when($userId, function($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->when($search, function($q) use ($search) {
                $q->whereHas('user', function($u) use ($search) {
                    $u->where('surname', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('other_names', 'like', "%{$search}%")
                        ->orWhere('membership_number', 'like', "%{$search}%");
                });
            })
            ->whereIn('status', ['active', 'defaulted', 'completed'])
            ->get();

        $rows = [];
        $sn = 1;

        $totals = [
            'loan_granted' => 0.0,
            'amount_repaid' => 0.0,
            'expected_amount_to_pay' => 0.0,
            'amount_defaulted' => 0.0,
            'loan_balance' => 0.0,
            'savings_balance' => 0.0,
        ];

        foreach ($loans as $loan) {
            $user = $loan->user;
            if (!$user) continue;

            $principal = (float)$loan->principal_amount;
            $paid = (float)$loan->paid_amount;
            $balance = (float)$loan->remaining_principal;

            $expectedToPay = max(0.0, $loan->getExpectedAmountTillNextInstallment($toDate) - $paid);
            $overdue = (float)$loan->getOverdueAmount($toDate);
            $defaultStartDate = $loan->getDefaultStartDate($toDate);

            $periodOfDefault = 'None';
            if ($defaultStartDate) {
                $days = (int) abs($toDate->diffInDays($defaultStartDate));
                $formattedDuration = DurationHelper::format($days);
                $periodOfDefault = $defaultStartDate->format('d-m-Y') . " ({$formattedDuration})";
            }

            $savingsBalance = (float)$user->contributions->sum('amount');

            $rows[] = [
                'sn' => $sn++,
                'member_name' => $user->full_name,
                'branch_name' => optional($user->branch)->name,
                'date_granted' => $loan->received_at ?: ($loan->approved_at ?: $loan->created_at),
                'loan_granted' => $principal,
                'amount_repaid' => $paid,
                'expected_amount_to_pay' => $expectedToPay,
                'amount_defaulted' => $overdue,
                'loan_balance' => $balance,
                'savings_balance' => $savingsBalance,
                'phone_number' => $user->phone,
                'period_of_default' => $periodOfDefault,
            ];

            $totals['loan_granted'] += $principal;
            $totals['amount_repaid'] += $paid;
            $totals['expected_amount_to_pay'] += $expectedToPay;
            $totals['amount_defaulted'] += $overdue;
            $totals['loan_balance'] += $balance;
            $totals['savings_balance'] += $savingsBalance;
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'month' => $monthStr,
            'year' => $yearStr,
            'cooperative_name' => 'AT-TAQWA C.I.C.S.',
        ];
    }

}
