<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecurringJournalResource\Pages;
use App\Models\RecurringJournal;
use App\Models\LedgerAccount;
use App\Services\AccrualService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class RecurringJournalResource extends Resource
{
    protected static ?string $model = RecurringJournal::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Recurring Journals';
    protected static ?int $navigationSort = 72;

    public static function shouldRegisterNavigation(): bool
    {
        // Reuse accruals_deferrals flag for recurring journals visibility
        return (bool) config('accounting.features.accruals_deferrals')
            && Schema::hasTable('recurring_journals');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('day_of_month')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->suffix('NGN'),
                Forms\Components\Select::make('debit_account_id')
                    ->label('Debit Account')
                    ->options(fn () => LedgerAccount::query()
                        ->where('is_active', true)
                        ->whereDoesntHave('children')
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn ($a) => [$a->id => ($a->code ? ($a->code.' - ') : '').$a->name])
                        ->toArray())
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('credit_account_id')
                    ->label('Credit Account')
                    ->options(fn () => LedgerAccount::query()
                        ->where('is_active', true)
                        ->whereDoesntHave('children')
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn ($a) => [$a->id => ($a->code ? ($a->code.' - ') : '').$a->name])
                        ->toArray())
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('reference_prefix')->maxLength(20),
                Forms\Components\Toggle::make('active')->inline(false)->default(true),
                Forms\Components\Placeholder::make('last_posted_at')
                    ->label('Last Posted At')
                    ->content(fn ($record) => $record?->last_posted_at)
                    ->visible(fn () => Schema::hasColumn('recurring_journals', 'last_posted_at')),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('day_of_month')->label('Day')->sortable(),
                TextColumn::make('amount')->money('ngn', true)->sortable(),
                TextColumn::make('last_posted_at')
                    ->dateTime()
                    ->label('Last Posted')
                    ->sortable()
                    ->visible(fn () => Schema::hasColumn('recurring_journals', 'last_posted_at')),
                TextColumn::make('active')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('runCurrentMonth')
                    ->label('Run Current Month')
                    ->icon('heroicon-o-play-circle')
                    ->visible(fn () => (bool) config('accounting.features.accruals_deferrals') && Schema::hasTable('recurring_journals'))
                    ->form([
                        Forms\Components\DatePicker::make('period')->label('As of Month')
                            ->default(now())
                            ->required(),
                        Forms\Components\Toggle::make('dry_run')->label('Dry Run (no posting)')->default(false),
                    ])
                    ->action(function (array $data) {
                        /** @var AccrualService $svc */
                        $svc = app(AccrualService::class);
                        $period = Carbon::parse($data['period'] ?? now());
                        $dry = (bool)($data['dry_run'] ?? false);
                        $svc->runRecurring($period, $dry);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListRecurringJournals::route('/'),
            'create' => Pages\CreateRecurringJournal::route('/create'),
            'edit' => Pages\EditRecurringJournal::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\RecurringJournalResource\Pages;

use App\Filament\Resources\RecurringJournalResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListRecurringJournals extends ListRecords
{
    protected static string $resource = RecurringJournalResource::class;
}

class CreateRecurringJournal extends CreateRecord
{
    protected static string $resource = RecurringJournalResource::class;
}

class EditRecurringJournal extends EditRecord
{
    protected static string $resource = RecurringJournalResource::class;
}
