<?php

namespace App\Filament\Resources\JuniorAccountResource\Pages;

use App\Filament\Resources\JuniorAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJuniorAccount extends EditRecord
{
    protected static string $resource = JuniorAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
