<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostingPeriodResource\Pages;
use App\Models\PostingPeriod;
use App\Support\Accounting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PostingPeriodResource extends Resource
{
    protected static ?string $model = PostingPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Posting Periods';
    protected static ?int $navigationSort = 52;

    public static function shouldRegisterNavigation(): bool
    {
        return Accounting::feature('fiscal_periods') && Accounting::tableExists('posting_periods');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('fiscal_year_id')
                    ->relationship('fiscalYear', 'name')
                    ->required(),
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\DatePicker::make('start_date')->required(),
                Forms\Components\DatePicker::make('end_date')->required(),
                Forms\Components\Toggle::make('is_open')->default(true),
                Forms\Components\TextInput::make('sequence')->numeric()->minValue(1)->required(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sequence')
            ->columns([
                TextColumn::make('fiscalYear.name')->label('Year')->sortable(),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('start_date')->date()->sortable(),
                TextColumn::make('end_date')->date()->sortable(),
                Tables\Columns\IconColumn::make('is_open')->boolean()->label('Open'),
                TextColumn::make('sequence')->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('close')
                        ->label('Close period')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_open' => false])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPostingPeriods::route('/'),
            'create' => Pages\CreatePostingPeriod::route('/create'),
            'edit' => Pages\EditPostingPeriod::route('/{record}/edit'),
        ];
    }
}
