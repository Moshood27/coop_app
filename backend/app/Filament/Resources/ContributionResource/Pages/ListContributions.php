<?php

namespace App\Filament\Resources\ContributionResource\Pages;

use App\Filament\Traits\HasWipeAction;

use App\Filament\Resources\ContributionResource;
use App\Filament\Resources\ContributionResource\Widgets\ContributionStats;
use App\Filament\Pages\ContributionBranchReport;
use App\Filament\Pages\SchemeBranchReport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;

class ListContributions extends ListRecords
{
    use HasWipeAction;

    protected static string $resource = ContributionResource::class;

    public function getSubheading(): ?string
    {
        return 'Monitor and track periodic contributions and member subscriptions.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ContributionStats::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Contribution')
                ->icon('heroicon-m-plus'),
            Actions\Action::make('branchReport')
                ->label('Contribution Report')
                ->icon('heroicon-m-document-chart-bar')
                ->color('success')
                ->url(fn () => ContributionBranchReport::getUrl()),
            Actions\Action::make('branchSchemeReport')
                ->label('Scheme Report')
                ->icon('heroicon-m-chart-pie')
                ->color('info')
                ->url(fn (): string => SchemeBranchReport::getUrl()),
            $this->getWipeHeaderAction()
                ->icon('heroicon-m-trash'),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Tables\Actions\Action::make('print')
                ->label('Print List')
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->extraAttributes(['onclick' => 'window.print()']),
        ];
    }
}
