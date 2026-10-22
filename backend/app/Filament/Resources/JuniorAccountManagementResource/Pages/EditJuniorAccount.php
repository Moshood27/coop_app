<?php

namespace App\Filament\Resources\JuniorAccountManagementResource\Pages;

use App\Filament\Resources\JuniorAccountManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJuniorAccount extends EditRecord
{
    protected static string $resource = JuniorAccountManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
