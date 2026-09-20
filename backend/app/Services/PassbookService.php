<?php

namespace App\Services;

use App\Models\User;
use App\Models\Scheme;
use App\Models\Setting;
use App\Models\QardHasan;
use Illuminate\Support\Carbon;

class PassbookService
{
    public function getPassbookData(User $user, int $year)
    {
        $startMonth = (int) Setting::get('financial_year_start_month', config('cooperative.financial_year_start_month', 1));

        $startDate = Carbon::create($year, $startMonth, 1, 0, 0, 0);
        $endDate = $startDate->copy()->addMonths(11)->endOfMonth();

        $yearContributions = $user->contributions()
            ->with('scheme')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('paid_at', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->whereNull('paid_at')->whereBetween('created_at', [$startDate, $endDate]);
                      });
            })
            ->where('status', 'success')
            ->orderByRaw('COALESCE(paid_at, created_at)')
            ->cursor();

        $bfContributions = $user->contributions()
            ->where(function($query) use ($startDate) {
                $query->where('paid_at', '<', $startDate)
                      ->orWhere(function($q) use ($startDate) {
                          $q->whereNull('paid_at')->where('created_at', '<', $startDate);
                      });
            })
            ->where('status', 'success')
            ->cursor();

        $monthMap = [];
        $monthLabels = [];
        $current = $startDate->copy();
        for ($i = 1; $i <= 12; $i++) {
            $monthMap[$current->format('Y-m')] = $i;
            $monthLabels[] = $current->format('M');
            $current->addMonth();
        }

        $userSchemeIds = $user->contributions()->where('status', 'success')->distinct()->pluck('scheme_id');
        $schemes = Scheme::where('active', true)->orWhereIn('id', $userSchemeIds)->orderBy('name')->get();

        // Aggregators to avoid memory-heavy collections
        $bfTotals = []; // [scheme_id => amount]
        $loanBfTotals = []; // [loan_id => amount]
        $yearTotals = []; // [scheme_id => [month_idx => amount]]
        $loanYearTotals = []; // [loan_id => [month_idx => amount]]
        $unlinkedLoanBf = 0;
        $unlinkedLoanYear = array_fill(1, 12, 0);

        $loanRepaymentScheme = $schemes->first(fn($s) => $s->name === 'Loan Repayment');

        foreach ($bfContributions as $con) {
            $amount = (float) $con->amount;
            if ($con->qard_hasan_id) {
                $loanBfTotals[$con->qard_hasan_id] = ($loanBfTotals[$con->qard_hasan_id] ?? 0) + $amount;
            } elseif ($loanRepaymentScheme && $con->scheme_id == $loanRepaymentScheme->id) {
                $unlinkedLoanBf += $amount;
            } else {
                $bfTotals[$con->scheme_id] = ($bfTotals[$con->scheme_id] ?? 0) + $amount;
            }
        }

        foreach ($yearContributions as $con) {
            $amount = (float) $con->amount;
            $date = $con->paid_at ?? $con->created_at;
            $key = $date->format('Y-m');
            $mIdx = $monthMap[$key] ?? null;

            if ($mIdx) {
                if ($con->qard_hasan_id) {
                    if (!isset($loanYearTotals[$con->qard_hasan_id])) {
                        $loanYearTotals[$con->qard_hasan_id] = array_fill(1, 12, 0);
                    }
                    $loanYearTotals[$con->qard_hasan_id][$mIdx] += $amount;
                } elseif ($loanRepaymentScheme && $con->scheme_id == $loanRepaymentScheme->id) {
                    $unlinkedLoanYear[$mIdx] += $amount;
                } else {
                    if (!isset($yearTotals[$con->scheme_id])) {
                        $yearTotals[$con->scheme_id] = array_fill(1, 12, 0);
                    }
                    $yearTotals[$con->scheme_id][$mIdx] += $amount;
                }
            }
        }

        $matrix = $schemes->filter(fn($s) => $s->name !== 'Loan Repayment')->map(function ($scheme) use ($bfTotals, $yearTotals) {
            $bf = $bfTotals[$scheme->id] ?? 0.0;
            $months = $yearTotals[$scheme->id] ?? array_fill(1, 12, 0);
            return [
                'scheme_name' => $scheme->name,
                'months' => $months,
                'bf' => $bf,
                'total' => $bf + array_sum($months),
                'is_exceptional' => false,
            ];
        })->values();

        if ($loanRepaymentScheme) {
            $activeLoans = $user->qardHasans()
                ->whereIn('status', ['active', 'defaulted', 'completed'])
                ->get();

            foreach ($activeLoans as $loan) {
                $bf = $loanBfTotals[$loan->id] ?? 0.0;
                $months = $loanYearTotals[$loan->id] ?? array_fill(1, 12, 0);
                $hasMonthlyActivity = array_sum($months) > 0;

                if (in_array($loan->status, ['active', 'defaulted']) || $hasMonthlyActivity) {
                    $matrix->push([
                        'scheme_name' => "Loan: " . ($loan->description ?: $loan->qard_id_string ?: "QH-{$loan->id}"),
                        'months' => $months,
                        'bf' => $bf,
                        'total' => $bf + array_sum($months),
                        'is_exceptional' => true,
                    ]);
                }
            }

            if (array_sum($unlinkedLoanYear) > 0 || $unlinkedLoanBf > 0) {
                $matrix->push([
                    'scheme_name' => 'Loan Repayment (Other)',
                    'months' => $unlinkedLoanYear,
                    'bf' => $unlinkedLoanBf,
                    'total' => $unlinkedLoanBf + array_sum($unlinkedLoanYear),
                    'is_exceptional' => true,
                ]);
            }
        }

        return [
            'year' => $year,
            'matrix' => $matrix,
            'month_labels' => $monthLabels,
            'grand_total' => $matrix->reject(fn($r) => $r['is_exceptional'])->sum('total'),
            'bf_total' => $matrix->reject(fn($r) => $r['is_exceptional'])->sum('bf'),
        ];
    }
}
