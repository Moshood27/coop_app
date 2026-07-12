<?php

namespace App\Filament\Resources\ContributionResource\Widgets;

use App\Filament\Resources\ContributionResource\Pages\ListContributions;
use App\Filament\Traits\SafeInteractsWithPageTable;
use App\Models\Contribution;
use App\Models\Scheme;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ContributionStats extends BaseWidget
{
    use SafeInteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListContributions::class;
    }

    protected function getStats(): array
    {
        $stats = [];

        $query = $this->getPageTableQuery();

        $totals = (clone $query)
            ->where('status', 'success')
            ->reorder()
            ->select('scheme_id', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('scheme_id')
            ->pluck('total_amount', 'scheme_id');

        $totalAll = $totals->sum();
        $stats[] = Stat::make('Overall Total', $this->formatCurrency($totalAll))
            ->description('All successful contributions')
            ->descriptionIcon('heroicon-m-banknotes')
            ->color('primary');

        $schemes = Scheme::where('active', true)
            ->orderBy('name')
            ->get();

        foreach ($schemes as $scheme) {
            $amount = $totals->get($scheme->id, 0);

            // Only show schemes that have contributions to avoid cluttering if there are many schemes
            if ($amount <= 0) continue;

            $stats[] = Stat::make($scheme->name, $this->formatCurrency($amount))
                ->description('Total Paid Contributions')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success');
        }

        return $stats;
    }

    protected function formatCurrency($value): string
    {
        return '₦' . $this->formatNumber($value);
    }

    protected function formatNumber($value): string
    {
        if ($value >= 1000000000000) {
            return number_format($value / 1000000000000, 2) . 'T';
        }
        if ($value >= 1000000000) {
            return number_format($value / 1000000000, 2) . 'B';
        }
        if ($value >= 1000000) {
            return number_format($value / 1000000, 2) . 'M';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 1) . 'K';
        }
        return number_format($value, 0);
    }
}
