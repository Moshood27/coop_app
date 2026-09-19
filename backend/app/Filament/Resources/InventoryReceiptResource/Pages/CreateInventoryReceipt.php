<?php

namespace App\Filament\Resources\InventoryReceiptResource\Pages;

use App\Filament\Resources\InventoryReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryReceipt extends CreateRecord
{
    protected static string $resource = InventoryReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'receipt';
        $data['total_cost'] = ($data['qty'] ?? 0) * ($data['unit_cost'] ?? 0);
        return $data;
    }
}
