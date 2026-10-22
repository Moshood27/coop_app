<?php

namespace App\Filament\Resources\JuniorAccountResource\Pages;

use App\Filament\Resources\JuniorAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJuniorAccounts extends ListRecords
{
    protected static string $resource = JuniorAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
