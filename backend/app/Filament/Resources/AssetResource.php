<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\Pages;
use App\Filament\Resources\AssetResource\RelationManagers\DepreciationsRelationManager;
use App\Models\Asset;
use App\Models\LedgerAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Assets';
    protected static ?int $navigationSort = 71;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable((new Asset())->getTable());
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        try {
            if (! Schema::hasTable((new Asset())->getTable())) {
                return Asset::query()->whereRaw('1 = 0');
            }
        } catch (\Throwable $e) {
            return Asset::query()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery();
    }

    public static function canViewAny(): bool
    {
        return optional(auth()->user())->can('fixed_assets.manage_assets') ?? false;
    }

    public static function canCreate(): bool
    {
        return optional(auth()->user())->can('fixed_assets.manage_assets') ?? false;
    }

    public static function canEdit($record): bool
    {
        return optional(auth()->user())->can('fixed_assets.manage_assets') ?? false;
    }

    public static function canDelete($record): bool
    {
        return optional(auth()->user())->can('fixed_assets.manage_assets') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('asset_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->label('Asset Code')
                    ->maxLength(100),
                Forms\Components\DatePicker::make('acquisition_date')->required(),
                Forms\Components\TextInput::make('acquisition_cost')->numeric()->required(),
                Forms\Components\TextInput::make('residual_value')->numeric()->default(0),
                Forms\Components\Select::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'disposed' => 'Disposed',
                    ])->default('active'),
                Forms\Components\Fieldset::make('GL Overrides (optional)')
                    ->schema([
                        Forms\Components\Select::make('asset_account_id')
                            ->label('Asset GL Account')
                            ->options(fn () => Schema::hasTable((new LedgerAccount())->getTable()) ? LedgerAccount::query()->orderBy('code')->pluck('name', 'id') : [])
                            ->searchable()->preload(),
                        Forms\Components\Select::make('accum_dep_account_id')
                            ->label('Accumulated Depreciation GL')
                            ->options(fn () => Schema::hasTable((new LedgerAccount())->getTable()) ? LedgerAccount::query()->orderBy('code')->pluck('name', 'id') : [])
                            ->searchable()->preload(),
                        Forms\Components\Select::make('dep_expense_account_id')
                            ->label('Depreciation Expense GL')
                            ->options(fn () => Schema::hasTable((new LedgerAccount())->getTable()) ? LedgerAccount::query()->orderBy('code')->pluck('name', 'id') : [])
                            ->searchable()->preload(),
                    ])->columns(3)->collapsed(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->sortable()->searchable(),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('category.name')->label('Category')->sortable()->searchable(),
                TextColumn::make('branch.name')->label('Branch')->toggleable(),
                TextColumn::make('acquisition_date')->date()->label('Acquired')->sortable(),
                TextColumn::make('acquisition_cost')->money('ngn', true)->label('Cost')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'secondary' => 'inactive',
                        'danger' => 'disposed',
                    ])->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('asset_category_id')->label('Category')->relationship('category', 'name'),
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

    public static function getRelations(): array
    {
        return [
            DepreciationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
