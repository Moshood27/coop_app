<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryAdjustmentResource\Pages;
use App\Models\InventoryTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class InventoryAdjustmentResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Inventory Adjustments';
    protected static ?int $navigationSort = 76;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable('inventory_transactions');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();
        if (! Schema::hasTable('inventory_transactions')) {
            return InventoryTransaction::query()->whereRaw('1=0');
        }
        return $q->where('type', 'adjustment');
    }

    public static function canViewAny(): bool
    {
        return optional(auth()->user())->can('inventory.adjustments') ?? false;
    }

    public static function canCreate(): bool
    {
        return optional(auth()->user())->can('inventory.adjustments') ?? false;
    }

    public static function canEdit($record): bool
    {
        return optional(auth()->user())->can('inventory.adjustments') ?? false;
    }

    public static function canDelete($record): bool
    {
        return optional(auth()->user())->can('inventory.adjustments') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Product')
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('branch_id')
                ->label('Branch')
                ->relationship('branch', 'name')
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('qty')
                ->numeric()->required()->label('Qty Delta'),
            Forms\Components\TextInput::make('unit_cost')
                ->numeric()->label('Unit Cost (required if increasing)'),
            Forms\Components\DateTimePicker::make('performed_at')
                ->label('Adjusted At')->default(now()),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch')->sortable(),
                Tables\Columns\TextColumn::make('qty')->numeric(2)->label('Qty Δ')->sortable(),
                Tables\Columns\TextColumn::make('unit_cost')->numeric(2)->label('Unit Cost')->sortable(),
                Tables\Columns\TextColumn::make('performed_at')->dateTime()->label('Adjusted At')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')->label('Branch')->relationship('branch', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryAdjustments::route('/'),
            'create' => Pages\CreateInventoryAdjustment::route('/create'),
            'edit' => Pages\EditInventoryAdjustment::route('/{record}/edit'),
        ];
    }
}
