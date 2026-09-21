<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\InventoryService;

class InventoryStockPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static string $view = 'filament.pages.inventory-stock-page';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Inventory Stock';
    protected static ?int $navigationSort = 77;

    public ?array $data = [];
    public ?array $rows = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable('inventory_transactions');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('branch_id')
                ->label('Branch')
                ->relationship('branch', 'name')
                ->searchable()
                ->preload(),
        ])->statePath('data');
    }

    public function load(InventoryService $inv): void
    {
        if (! Schema::hasTable('inventory_transactions')) {
            $this->rows = null;
            Notification::make()->title('Migrations pending')->warning()->body('Run migrations after deploy to enable Inventory.')->send();
            return;
        }
        $branchId = data_get($this->form->getState(), 'branch_id');
        // naive: list products with any inventory txns
        $products = DB::table('inventory_transactions')
            ->select('product_id')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('product_id')
            ->pluck('product_id');

        $names = DB::table('products')->whereIn('id', $products)->pluck('name', 'id');
        $this->rows = [];
        foreach ($products as $pid) {
            $soh = $inv->getStockOnHand((int)$pid, $branchId ? (int)$branchId : null);
            $avg = $inv->getAverageCost((int)$pid, $branchId ? (int)$branchId : null);
            $this->rows[] = [
                'product_id' => (int)$pid,
                'name' => (string)($names[$pid] ?? ('#'.$pid)),
                'stock_on_hand' => $soh,
                'avg_cost' => $avg,
                'inventory_value' => $soh * $avg,
            ];
        }
        usort($this->rows, fn($a,$b) => strcmp($a['name'], $b['name']));
    }
}
