<?php

namespace App\Filament\Resources\BankStatementResource\Pages;

use App\Filament\Resources\BankStatementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankStatement extends CreateRecord
{
    protected static string $resource = BankStatementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = auth()->id();
        return $data;
    }
}
