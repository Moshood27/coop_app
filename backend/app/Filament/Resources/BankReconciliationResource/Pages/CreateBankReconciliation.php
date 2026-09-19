<?php

namespace App\Filament\Resources\BankReconciliationResource\Pages;

use App\Filament\Resources\BankReconciliationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankReconciliation extends CreateRecord
{
    protected static string $resource = BankReconciliationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reconciled_by'] = auth()->id();
        return $data;
    }
}
