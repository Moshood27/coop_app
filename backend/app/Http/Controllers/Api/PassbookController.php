<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PassbookController extends Controller
{
    public function getMatrix(Request $request, int $year)
    {
        $user = $request->user();

        $startOfYear = Carbon::create($year, 1, 1, 0, 0, 0);

        $yearContributions = $user->contributions()
            ->where(function($query) use ($year) {
                $query->whereYear('paid_at', $year)
                      ->orWhere(function($q) use ($year) {
                          $q->whereNull('paid_at')->whereYear('created_at', $year);
                      });
            })
            ->where('status', 'success')
            ->get();

        $bfContributions = $user->contributions()
            ->where(function($query) use ($startOfYear) {
                $query->where('paid_at', '<', $startOfYear)
                      ->orWhere(function($q) use ($startOfYear) {
                          $q->whereNull('paid_at')->where('created_at', '<', $startOfYear);
                      });
            })
            ->where('status', 'success')
            ->get();

        $userSchemeIds = $user->contributions()->where('status', 'success')->distinct()->pluck('scheme_id');
        $schemes = Scheme::where('active', true)->orWhereIn('id', $userSchemeIds)->orderBy('name')->get();

        $matrix = $schemes->map(function ($scheme) use ($yearContributions, $bfContributions) {
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

            foreach ($yearContributions as $con) {
                if ($con->scheme_id == $scheme->id) {
                    $date = $con->paid_at ?? $con->created_at;
                    $month = $date->month;
                    $row['months'][$month] += (float) $con->amount;
                    $row['total'] += (float) $con->amount;
                }
            }

            return $row;
        });

        return response()->json([
            'year' => $year,
            'matrix' => $matrix,
            'grand_total' => $matrix->sum('total'),
            'bf_total' => $matrix->sum('bf'),
        ]);
    }
}
