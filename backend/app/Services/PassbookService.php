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
            ->get();

        $bfContributions = $user->contributions()
            ->where(function($query) use ($startDate) {
                $query->where('paid_at', '<', $startDate)
                      ->orWhere(function($q) use ($startDate) {
                          $q->whereNull('paid_at')->where('created_at', '<', $startDate);
                      });
            })
            ->where('status', 'success')
            ->get();

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

        $matrix = $schemes->filter(fn($s) => $s->name !== 'Loan Repayment')->map(function ($scheme) use ($yearContributions, $bfContributions, $monthMap) {
            $row = [
                'scheme_name' => $scheme->name,
                'months' => array_fill(1, 12, 0),
                'bf' => 0.0,
                'total' => 0.0,
                'is_exceptional' => false,
            ];

            foreach ($bfContributions as $con) {
                if ($con->scheme_id == $scheme->id) {
                    $row['bf'] += (float) $con->amount;
                }
            }

            $row['total'] = $row['bf'];

            foreach ($yearContributions as $con) {
                if ($con->scheme_id == $scheme->id) {
                    $date = $con->paid_at ?? $con->created_at;
                    $key = $date->format('Y-m');
                    if (isset($monthMap[$key])) {
                        $mIdx = $monthMap[$key];
                        $row['months'][$mIdx] += (float) $con->amount;
                        $row['total'] += (float) $con->amount;
                    }
                }
            }

            return $row;
        })->values();

        // Handle Loan Repayments Exceptionally
        $loanRepaymentScheme = $schemes->first(fn($s) => $s->name === 'Loan Repayment');
        if ($loanRepaymentScheme) {
            $activeLoans = $user->qardHasans()
                ->whereIn('status', ['active', 'defaulted', 'completed'])
                ->get();

            foreach ($activeLoans as $loan) {
                $row = [
                    'scheme_name' => "Loan: " . ($loan->description ?: $loan->qard_id_string ?: "QH-{$loan->id}"),
                    'months' => array_fill(1, 12, 0),
                    'bf' => 0.0,
                    'total' => 0.0,
                    'is_exceptional' => true,
                ];

                // Repayments before start date
                $row['bf'] = (float) $yearContributions->where('qard_hasan_id', $loan->id)->where('paid_at', '<', $startDate)->sum('amount')
                             + (float) $bfContributions->where('qard_hasan_id', $loan->id)->sum('amount');

                // Wait, yearContributions are already filtered by date range.
                // bfContributions are those before startDate.
                // So bf is just sum of bfContributions for this loan.
                $row['bf'] = (float) $bfContributions->where('qard_hasan_id', $loan->id)->sum('amount');
                $row['total'] = $row['bf'];

                foreach ($yearContributions as $con) {
                    if ($con->qard_hasan_id == $loan->id) {
                        $date = $con->paid_at ?? $con->created_at;
                        $key = $date->format('Y-m');
                        if (isset($monthMap[$key])) {
                            $mIdx = $monthMap[$key];
                            $row['months'][$mIdx] += (float) $con->amount;
                            $row['total'] += (float) $con->amount;
                        }
                    }
                }

                if ($row['total'] > 0 || in_array($loan->status, ['active', 'defaulted'])) {
                    $matrix->push($row);
                }
            }

            // Handle unlinked loan repayments
            $unlinkedRow = [
                'scheme_name' => 'Loan Repayment (Other)',
                'months' => array_fill(1, 12, 0),
                'bf' => 0.0,
                'total' => 0.0,
                'is_exceptional' => true,
            ];

            foreach ($bfContributions as $con) {
                if ($con->scheme_id == $loanRepaymentScheme->id && empty($con->qard_hasan_id)) {
                    $unlinkedRow['bf'] += (float) $con->amount;
                }
            }
            $unlinkedRow['total'] = $unlinkedRow['bf'];

            foreach ($yearContributions as $con) {
                if ($con->scheme_id == $loanRepaymentScheme->id && empty($con->qard_hasan_id)) {
                    $date = $con->paid_at ?? $con->created_at;
                    $key = $date->format('Y-m');
                    if (isset($monthMap[$key])) {
                        $mIdx = $monthMap[$key];
                        $unlinkedRow['months'][$mIdx] += (float) $con->amount;
                        $unlinkedRow['total'] += (float) $con->amount;
                    }
                }
            }

            if ($unlinkedRow['total'] > 0) {
                $matrix->push($unlinkedRow);
            }
        }

        return [
            'year' => $year,
            'matrix' => $matrix,
            'month_labels' => $monthLabels,
            'grand_total' => $matrix->reject(fn($r) => $r['is_exceptional'])->sum('total'),
            'bf_total' => $matrix->reject(fn($r) => $r['is_exceptional'])->sum('bf'),
            'year_contributions' => $yearContributions,
        ];
    }
}
