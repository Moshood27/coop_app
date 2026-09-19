<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedgerJournalResource\Pages;
use App\Models\LedgerJournal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Resources\LedgerJournalResource\RelationManagers\AttachmentsRelationManager;
use Illuminate\Support\Facades\Artisan;

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
                    ->default(now()),
                Forms\Components\TextInput::make('reference')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),

                Forms\Components\Section::make('Entries')
                    ->schema([
                        Forms\Components\Repeater::make('entries')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('ledger_account_id')
                                    ->relationship('account', 'name')
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
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('reference')->searchable(),
                TextColumn::make('description')->limit(50)->searchable(),
                TextColumn::make('entries_sum_debit')
                    ->sum('entries', 'debit')
                    ->money('ngn', true)
                    ->label('Total Debit'),
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
            ->headerActions([
                Tables\Actions\Action::make('run_auto_reversals')
                    ->label('Run Auto-Reversals')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Forms\Components\DatePicker::make('date')->label('As Of Date')->default(now()->toDateString()),
                        Forms\Components\TextInput::make('journal')->numeric()->label('Specific Journal ID')->helperText('Optional: reverse a specific journal'),
                        Forms\Components\Toggle::make('dry_run')->default(false),
                    ])
                    ->action(function (array $data) {
                        try {
                            if (!\Illuminate\Support\Facades\Schema::hasTable('ledger_journals')) {
                                \Filament\Notifications\Notification::make('auto_reverse_missing')
                                    ->title('Migrations pending')
                                    ->warning()
                                    ->body('ledger_journals table missing')
                                    ->send();
                                return;
                            }
                            Artisan::call('accounting:auto-reverse', array_filter([
                                '--date' => $data['date'] ?? null,
                                '--journal' => $data['journal'] ?? null,
                                '--dry-run' => ($data['dry_run'] ?? false) ? true : null,
                            ]));
                            \Filament\Notifications\Notification::make('auto_reverse_ok')
                                ->title('Auto-Reversals Executed')
                                ->success()
                                ->body(trim(Artisan::output()))
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make('auto_reverse_err')
                                ->title('Auto-Reversals Failed')
                                ->danger()
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListLedgerJournals::route('/'),
            'create' => Pages\CreateLedgerJournal::route('/create'),
            'edit' => Pages\EditLedgerJournal::route('/{record}/edit'),
            'view' => Pages\ViewLedgerJournal::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            AttachmentsRelationManager::class,
        ];
    }
}
