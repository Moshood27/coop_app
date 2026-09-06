<?php

namespace App\Filament\Resources\AdministrativeChargeResource\Pages;

use App\Filament\Resources\AdministrativeChargeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdministrativeCharges extends ListRecords
{
    protected static string $resource = AdministrativeChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions like "Process Monthly Charges" could be added here
            Actions\Action::make('processMonthlyCharges')
                ->label('Run Monthly Accrual')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function() {
                    \App\Jobs\ProcessAdministrativeChargesJob::dispatch();

                    \Filament\Notifications\Notification::make()
                        ->title('Processing Started')
                        ->body("The monthly administrative charges are being processed in the background. You will be notified once complete (check system logs).")
                        ->info()
                        ->send();
                })
        ];
    }
}
