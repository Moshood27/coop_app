<?php

namespace App\Filament\Resources\InventoryReceiptResource\Pages;

use App\Filament\Resources\InventoryReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInventoryReceipts extends ListRecords
{
    protected static string $resource = InventoryReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
