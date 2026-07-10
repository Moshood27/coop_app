<?php

namespace App\Filament\Resources\WalletTransactionResource\Widgets;

use App\Filament\Resources\WalletTransactionResource\Pages\ListWalletTransactions;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WalletStatsOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListWalletTransactions::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();

        $credit = (float) (clone $query)->where('type', 'credit')->sum('amount');
        $debit = (float) (clone $query)->where('type', 'debit')->sum('amount');
        $net = $credit - $debit;

        return [
            Stat::make('Total Credits', $this->formatCurrency($credit))
                ->description('Sum of all inflows in view')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Debits', $this->formatCurrency($debit))
                ->description('Sum of all outflows in view')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            Stat::make('Net Movement', $this->formatCurrency($net))
                ->description('Balance of transactions in view')
                ->descriptionIcon($net >= 0 ? 'heroicon-m-banknotes' : 'heroicon-m-credit-card')
                ->color($net >= 0 ? 'success' : 'danger'),
        ];
    }

    protected function formatCurrency($value): string
    {
        $prefix = $value < 0 ? '-₦' : '₦';
        return $prefix . $this->formatNumber(abs($value));
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
