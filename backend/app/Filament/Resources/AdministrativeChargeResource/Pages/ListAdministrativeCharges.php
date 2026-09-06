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
                    $service = app(\App\Services\AdministrativeChargeService::class);
                    $stats = $service->processMonthlyCharges();

                    \Filament\Notifications\Notification::make()
                        ->title('Administrative Charges Processed')
                        ->body("Processed {$stats['total_users']} users. Accrued: ₦" . number_format($stats['accrued'] * 300, 2) . " (approx). Deducted: ₦" . number_format($stats['total_deducted_amount'], 2))
                        ->success()
                        ->send();
                })
        ];
    }
}
