<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExchangeRateResource\Pages;
use App\Support\Accounting as AccountingSupport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ExchangeRateResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Exchange Rates';
    protected static ?int $navigationSort = 63;

    public static function shouldRegisterNavigation(): bool
    {
        return AccountingSupport::feature('multicurrency') && AccountingSupport::tableExists('exchange_rates');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('base_currency')->maxLength(3)->required(),
                Forms\Components\TextInput::make('quote_currency')->maxLength(3)->required(),
                Forms\Components\DatePicker::make('rate_date')->required(),
                Forms\Components\TextInput::make('rate')->numeric()->required(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('base_currency')->label('From')->searchable(),
                TextColumn::make('quote_currency')->label('To')->searchable(),
                TextColumn::make('rate_date')->date()->sortable(),
                TextColumn::make('rate')->numeric()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Use query builder against the exchange_rates table since we don't have a model
        // Filament supports using a Model; but to avoid adding a model, we use DB table via a simple anonymous model
        $model = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'exchange_rates';
            public $timestamps = false;
            protected $fillable = ['base_currency', 'quote_currency', 'rate_date', 'rate'];
            protected $casts = [
                'rate_date' => 'date',
                'rate' => 'decimal:6',
            ];
        };
        /** @var \Illuminate\Database\Eloquent\Model $model */
        return $model->newQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExchangeRates::route('/'),
            'create' => Pages\CreateExchangeRate::route('/create'),
            'edit' => Pages\EditExchangeRate::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\ExchangeRateResource\Pages;

use App\Filament\Resources\ExchangeRateResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListExchangeRates extends ListRecords
{
    protected static string $resource = ExchangeRateResource::class;
}

class CreateExchangeRate extends CreateRecord
{
    protected static string $resource = ExchangeRateResource::class;
}

class EditExchangeRate extends EditRecord
{
    protected static string $resource = ExchangeRateResource::class;
}
