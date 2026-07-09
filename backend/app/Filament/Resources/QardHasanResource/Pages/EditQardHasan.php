<?php

namespace App\Filament\Resources\QardHasanResource\Pages;

use App\Filament\Resources\QardHasanResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditQardHasan extends EditRecord
{
    protected static string $resource = QardHasanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->successNotificationTitle('Loan deleted successfully'),
        ];
    }
}
