<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxRateResource\Pages;
use App\Models\TaxRate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Tax Rates';
    protected static ?int $navigationSort = 95;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable((new TaxRate())->getTable());
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        try {
            if (! Schema::hasTable((new TaxRate())->getTable())) {
                return TaxRate::query()->whereRaw('1 = 0');
            }
        } catch (\Throwable $e) {
            return TaxRate::query()->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return optional(auth()->user())->can('tax.manage_rates') ?? false;
    }

    public static function canCreate(): bool
    {
        return optional(auth()->user())->can('tax.manage_rates') ?? false;
    }

    public static function canEdit($record): bool
    {
        return optional(auth()->user())->can('tax.manage_rates') ?? false;
    }

    public static function canDelete($record): bool
    {
        return optional(auth()->user())->can('tax.manage_rates') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('percent')->numeric()->step('0.01')->minValue(0)->maxValue(100)->required(),
                Forms\Components\TextInput::make('country')->maxLength(2)->label('Country (ISO2)'),
                Forms\Components\DatePicker::make('effective_from')->native(false),
                Forms\Components\DatePicker::make('effective_to')->native(false),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('percent')->label('%')->sortable(),
                TextColumn::make('country')->label('Country'),
                TextColumn::make('effective_from')->date()->label('From')->toggleable(),
                TextColumn::make('effective_to')->date()->label('To')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
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
            'index' => Pages\ListTaxRates::route('/'),
            'create' => Pages\CreateTaxRate::route('/create'),
            'edit' => Pages\EditTaxRate::route('/{record}/edit'),
        ];
    }
}
