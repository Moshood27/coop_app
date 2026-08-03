<?php

namespace App\Filament\Resources\MonthClosingResource\Pages;

use App\Filament\Resources\MonthClosingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMonthClosings extends ListRecords
{
    protected static string $resource = MonthClosingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Standard CreateAction is disabled in resource,
            // but we have a custom header action in the table.
        ];
    }
}
