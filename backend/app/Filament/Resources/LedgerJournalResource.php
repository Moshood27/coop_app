<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedgerJournalResource\Pages;
use App\Models\LedgerJournal;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Support\Accounting as AccountingSupport;

class LedgerJournalResource extends Resource
{
    protected static ?string $model = LedgerJournal::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Journal Entries';
    protected static ?int $navigationSort = 51;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->default(now())
                    ->disabled(fn ($record) => $record && method_exists($record, 'isPosted') && $record->isPosted()),
                Forms\Components\TextInput::make('reference')
                    ->maxLength(255)
                    ->disabled(fn ($record) => $record && method_exists($record, 'isPosted') && $record->isPosted()),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull()
                    ->disabled(fn ($record) => $record && method_exists($record, 'isPosted') && $record->isPosted()),

                Forms\Components\Section::make('Entries')
                    ->schema([
                        Forms\Components\Repeater::make('entries')
                            ->relationship()
                            ->disabled(fn ($record) => $record && method_exists($record, 'isPosted') && $record->isPosted())
                            ->schema([
                                Forms\Components\Select::make('ledger_account_id')
                                    ->relationship('account', 'name', fn (Builder $query) => $query
                                        ->doesntHave('children')
                                        ->where('is_active', true))
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\TextInput::make('debit')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                Forms\Components\TextInput::make('credit')
                                    ->numeric()
                                    ->default(0)
                                    ->required(),
                                Forms\Components\TextInput::make('description')
                                    ->maxLength(255),
                                // Optional analytic dimensions (schema-aware)
                                Forms\Components\TextInput::make('branch_id')
                                    ->label('Branch ID')
                                    ->numeric()
                                    ->visible(fn () => AccountingSupport::columnExists('ledger_entries', 'branch_id')),
                                Forms\Components\TextInput::make('project_id')
                                    ->label('Project ID')
                                    ->numeric()
                                    ->visible(fn () => AccountingSupport::columnExists('ledger_entries', 'project_id')),
                                Forms\Components\TextInput::make('fund_id')
                                    ->label('Fund ID')
                                    ->numeric()
                                    ->visible(fn () => AccountingSupport::columnExists('ledger_entries', 'fund_id')),
                            ])
                            ->columns(4)
                            ->minItems(2)
                            ->defaultItems(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Number')
                    ->visible(fn () => \App\Support\Accounting::columnExists('ledger_journals', 'number'))
                    ->sortable(),
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('reference')->searchable(),
                TextColumn::make('description')->limit(50)->searchable(),
                TextColumn::make('entries_sum_debit')
                    ->sum('entries', 'debit')
                    ->money('ngn', true)
                    ->label('Total Debit'),
                TextColumn::make('status_label')
                    ->label('Status')
                    ->state(fn (LedgerJournal $record) => $record->isPosted() ? 'Posted' : 'Draft')
                    ->badge()
                    ->colors([
                        'success' => fn ($state) => $state === 'Posted',
                        'warning' => fn ($state) => $state === 'Draft',
                    ])
                    ->toggleable(),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (LedgerJournal $record) => !$record->isPosted()),
                Tables\Actions\Action::make('post')
                    ->label('Post')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (LedgerJournal $record) => !$record->isPosted() && (AccountingSupport::columnExists('ledger_journals','posted_at') || AccountingSupport::columnExists('ledger_journals','status')))
                    ->action(function (LedgerJournal $record): void {
                        app(\App\Services\LedgerService::class)->postJournal($record, auth()->id());
                        // Optional numbering
                        app(\App\Services\JournalNumberingService::class)->assignNumber($record);
                    }),
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
            'index' => Pages\ListLedgerJournals::route('/'),
            'create' => Pages\CreateLedgerJournal::route('/create'),
            'edit' => Pages\EditLedgerJournal::route('/{record}/edit'),
            'view' => Pages\ViewLedgerJournal::route('/{record}'),
        ];
    }
}
