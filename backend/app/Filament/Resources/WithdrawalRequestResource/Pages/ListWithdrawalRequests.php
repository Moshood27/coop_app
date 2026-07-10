<?php

namespace App\Filament\Resources\WithdrawalRequestResource\Pages;

use App\Filament\Traits\HasWipeAction;

use App\Filament\Resources\WithdrawalRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListWithdrawalRequests extends ListRecords
{
    use HasWipeAction;

    protected static string $resource = WithdrawalRequestResource::class;

    public function getSubheading(): ?string
    {
        return 'Review and approve member requests to withdraw funds from wallets.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Request')
                ->icon('heroicon-m-plus'),
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
