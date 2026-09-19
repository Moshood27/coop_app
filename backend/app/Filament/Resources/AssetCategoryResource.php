<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetCategoryResource\Pages;
use App\Models\AssetCategory;
use App\Models\LedgerAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class AssetCategoryResource extends Resource
{
    protected static ?string $model = AssetCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Asset Categories';
    protected static ?int $navigationSort = 70;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable((new AssetCategory())->getTable());
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        try {
            if (! Schema::hasTable((new AssetCategory())->getTable())) {
                return AssetCategory::query()->whereRaw('1 = 0');
            }
        } catch (\Throwable $e) {
            return AssetCategory::query()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery();
    }

    public static function canViewAny(): bool
    {
        return optional(auth()->user())->can('fixed_assets.manage_categories') ?? false;
    }

    public static function canCreate(): bool
    {
        return optional(auth()->user())->can('fixed_assets.manage_categories') ?? false;
    }

    public static function canEdit($record): bool
    {
        return optional(auth()->user())->can('fixed_assets.manage_categories') ?? false;
    }

    public static function canDelete($record): bool
    {
        return optional(auth()->user())->can('fixed_assets.manage_categories') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('method')
                    ->options([
                        'straight_line' => 'Straight Line',
                        'reducing_balance' => 'Reducing Balance',
                    ])->required(),
                Forms\Components\TextInput::make('useful_life_months')
                    ->label('Useful Life (months)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Forms\Components\TextInput::make('rate_percent')
                    ->label('Rate % (for RB)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                Forms\Components\Select::make('asset_account_id')
                    ->label('Asset GL Account')
                    ->options(fn () => Schema::hasTable((new LedgerAccount())->getTable()) ? LedgerAccount::query()->orderBy('code')->pluck('name', 'id') : [])
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('accum_dep_account_id')
                    ->label('Accumulated Depreciation GL')
                    ->options(fn () => Schema::hasTable((new LedgerAccount())->getTable()) ? LedgerAccount::query()->orderBy('code')->pluck('name', 'id') : [])
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('dep_expense_account_id')
                    ->label('Depreciation Expense GL')
                    ->options(fn () => Schema::hasTable((new LedgerAccount())->getTable()) ? LedgerAccount::query()->orderBy('code')->pluck('name', 'id') : [])
                    ->searchable()
                    ->preload()
                    ->required(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('method')->badge()->sortable(),
                TextColumn::make('useful_life_months')->label('Life (mths)')->sortable(),
                TextColumn::make('rate_percent')->label('Rate %')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        'straight_line' => 'Straight Line',
                        'reducing_balance' => 'Reducing Balance',
                    ]),
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
            'index' => Pages\ListAssetCategories::route('/'),
            'create' => Pages\CreateAssetCategory::route('/create'),
            'edit' => Pages\EditAssetCategory::route('/{record}/edit'),
        ];
    }
}
