<?php

namespace App\Filament\Resources\InventoryAdjustmentResource\Pages;

use App\Filament\Resources\InventoryAdjustmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryAdjustment extends CreateRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'adjustment';
        if (!isset($data['unit_cost']) || $data['unit_cost'] === null) {
            // allow null; service will compute average for negatives
            $data['unit_cost'] = $data['unit_cost'] ?? 0;
        }
        $data['total_cost'] = ($data['qty'] ?? 0) * ($data['unit_cost'] ?? 0);
        return $data;
    }
}
