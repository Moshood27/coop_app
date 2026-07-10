<?php

namespace App\Filament\Widgets;

use App\Models\Contribution;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalCollectionsToday extends BaseWidget
{
    protected static ?int $sort = -11;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return 'Daily Collections';
    }

    protected function getStats(): array
    {
        $today = Carbon::today();

        $sum = Contribution::query()
            ->whereDate('created_at', $today)
            ->where('status', 'success')
            ->sum('amount');

        return [
            Stat::make('Total Collections Today', $this->formatCurrency($sum))
                ->description('Successful contributions today')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),
        ];
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
