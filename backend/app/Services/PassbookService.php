<?php

namespace App\Services;

use App\Models\User;
use App\Models\Scheme;
use App\Models\Setting;
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

        $matrix = $schemes->map(function ($scheme) use ($yearContributions, $bfContributions, $monthMap) {
            $row = [
                'scheme_name' => $scheme->name,
                'months' => array_fill(1, 12, 0),
                'bf' => 0.0,
                'total' => 0.0,
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
        });

        return [
            'year' => $year,
            'matrix' => $matrix,
            'month_labels' => $monthLabels,
            'grand_total' => $matrix->sum('total'),
            'bf_total' => $matrix->sum('bf'),
            'year_contributions' => $yearContributions,
        ];
    }
}
