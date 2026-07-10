<?php

namespace App\Filament\Widgets;

use App\Models\Contribution;
use App\Models\QardHasan;
use App\Models\TakafulPoolEntry;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\GoldSilverPriceService;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FinanceSnapshot extends BaseWidget
{
    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return 'Financial Overview';
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        $mtd = Contribution::query()
            ->where('status', 'success')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');

        $collectionsTrend = Contribution::query()
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subDays(7))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total')
            ->toArray();

        $activePortfolio = QardHasan::query()
            ->whereIn('status', ['active', 'defaulted'])
            ->get()
            ->sum(function ($q) {
                return (float) $q->principal_amount - (float) $q->paid_amount;
            });

        $totalLiability = (float) User::sum('balance');

        $poolBalance = TakafulPoolEntry::balance();

        $pendingWithdrawals = WithdrawalRequest::where('status', 'pending')->count();
        $pendingAmount = (float) WithdrawalRequest::where('status', 'pending')->sum('amount');

        $overdueCount = QardHasan::whereIn('status', ['active', 'defaulted'])
            ->get()
            ->filter(fn($q) => $q->getOverdueDays() > 0)
            ->count();

        $totalGold = (float) User::sum('gold_balance');
        $goldPriceData = (new GoldSilverPriceService())->getGoldPriceData();
        $goldPrice = $goldPriceData['sell_price_ngn'] ?? 0;
        $goldValue = $totalGold * $goldPrice;

        return [
            Stat::make('Collections (MTD)', $this->formatCurrency($mtd))
                ->description('Successful contributions this month')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($collectionsTrend)
                ->color('success'),

            Stat::make('Loan Portfolio', $this->formatCurrency($activePortfolio))
                ->description('Active outstanding principal')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('warning'),

            Stat::make('Member Wallets', $this->formatCurrency($totalLiability))
                ->description('Total liquidity owed to members')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger'),

            Stat::make('Gold Reserves', number_format($totalGold, 2) . ' g')
                ->description('₦' . $this->formatNumber($goldValue) . ' @ current price')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('warning'),

            Stat::make('Takaful Pool', $this->formatCurrency($poolBalance))
                ->description('Funds for loan settlements')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('info'),

            Stat::make('Pending Withdrawals', $pendingWithdrawals)
                ->description($this->formatCurrency($pendingAmount) . ' awaiting approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingWithdrawals > 0 ? 'danger' : 'gray'),

            Stat::make('Overdue Loans', $overdueCount)
                ->description('Members at risk of default')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : 'gray'),
        ];
    }

    protected function formatCurrency($value): string
    {
        return '₦' . $this->formatNumber($value);
    }

    protected function formatNumber($value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 2) . 'M';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 1) . 'K';
        }
        return number_format($value, 0);
    }
}
