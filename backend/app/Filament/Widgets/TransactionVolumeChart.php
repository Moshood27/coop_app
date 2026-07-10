<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TransactionVolumeChart extends ChartWidget
{
    protected static ?string $heading = 'Daily Transaction Volume (30d)';
    protected static ?int $sort = -1;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $end = Carbon::today();
        $start = $end->copy()->subDays(29);

        $rows = DB::table('wallet_transactions')
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('SUM(amount) as total'))
            ->groupBy('d')
            ->pluck('total', 'd');

        $labels = [];
        $series = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $d = $date->toDateString();
            $labels[] = $date->format('M d');
            $series[] = (float) ($rows[$d] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Volume (₦)',
                    'data' => $series,
                    'borderColor' => '#0ea5e9',
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'ticks' => [
                        'callback' => "function(value) { return '₦' + value.toLocaleString() }",
                    ],
                ],
            ],
        ];
    }
}
