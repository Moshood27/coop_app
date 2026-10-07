<?php

namespace App\Filament\Resources\ChargeAndFineManagementResource\Pages;

use App\Filament\Resources\ChargeAndFineManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChargeAndFineManagements extends ListRecords
{
    protected static string $resource = ChargeAndFineManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions like export can be added here
        ];
    }
}
