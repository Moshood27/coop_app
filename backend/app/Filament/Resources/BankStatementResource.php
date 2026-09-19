<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankStatementResource\Pages;
use App\Filament\Resources\BankStatementResource\RelationManagers\LinesRelationManager;
use App\Models\BankStatement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class BankStatementResource extends Resource
{
    protected static ?string $model = BankStatement::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Bank Statements';
    protected static ?int $navigationSort = 61;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable((new BankStatement())->getTable());
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        try {
            if (! Schema::hasTable((new BankStatement())->getTable())) {
                return BankStatement::query()->whereRaw('1 = 0');
            }
        } catch (\Throwable $e) {
            return BankStatement::query()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery();
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return ($u?->can('bank_statements.create') || $u?->can('bank_statements.import') || $u?->can('bank_statements.automatch')) ?? false;
    }

    public static function canCreate(): bool
    {
        return optional(auth()->user())->can('bank_statements.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return optional(auth()->user())->can('bank_statements.create') ?? false;
    }

    public static function canDelete($record): bool
    {
        return optional(auth()->user())->can('bank_statements.create') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('bank_account_id')
                    ->label('Bank Account')
                    ->relationship('bankAccount', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('period_from')
                    ->required(),
                Forms\Components\DatePicker::make('period_to')
                    ->required(),
                Forms\Components\TextInput::make('opening_balance')
                    ->numeric()
                    ->label('Opening Balance')
                    ->required(),
                Forms\Components\TextInput::make('closing_balance')
                    ->numeric()
                    ->label('Closing Balance')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'imported' => 'Imported',
                        'in_progress' => 'In Progress',
                        'finalized' => 'Finalized',
                    ])->default('draft'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bankAccount.name')->label('Bank Account')->sortable()->searchable(),
                TextColumn::make('period_from')->date()->label('From')->sortable(),
                TextColumn::make('period_to')->date()->label('To')->sortable(),
                TextColumn::make('opening_balance')->money('ngn', true)->label('Opening'),
                TextColumn::make('closing_balance')->money('ngn', true)->label('Closing'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'imported',
                        'info' => 'in_progress',
                        'success' => 'finalized',
                    ])->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bank_account_id')->label('Bank Account')->relationship('bankAccount', 'name'),
                Tables\Filters\TernaryFilter::make('status')->label('Finalized')
                    ->trueLabel('Finalized')
                    ->falseLabel('Not Finalized')
                    ->queries(
                        true: fn (Builder $q) => $q->where('status', 'finalized'),
                        false: fn (Builder $q) => $q->where('status', '!=', 'finalized'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn () => optional(auth()->user())->can('bank_statements.create') ?? false),
                Tables\Actions\Action::make('importCsv')
                    ->label('Import CSV (paste)')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\Textarea::make('csv')
                            ->rows(10)
                            ->required()
                            ->helperText('Paste CSV with headers: date,amount,description,reference')
                    ])
                    ->visible(fn () => optional(auth()->user())->can('bank_statements.import') ?? false)
                    ->action(function (BankStatement $record, array $data) {
                        try {
                            $rows = [];
                            $lines = preg_split("/(\r?\n)/", (string)($data['csv'] ?? ''));
                            $headers = [];
                            foreach ($lines as $i => $line) {
                                $line = trim($line);
                                if ($line === '') continue;
                                $cols = str_getcsv($line);
                                if ($i === 0) { $headers = array_map('strtolower', $cols); continue; }
                                $row = [];
                                foreach ($cols as $idx => $val) {
                                    $key = $headers[$idx] ?? 'col'.$idx;
                                    $row[$key] = $val;
                                }
                                $rows[] = $row;
                            }
                            $svc = app(\App\Services\BankReconciliationService::class);
                            $count = $svc->importLines($record, $rows);
                            \Filament\Notifications\Notification::make()->title('Imported '.$count.' line(s)')->success()->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('autoMatch')
                    ->label('Auto-Match')
                    ->icon('heroicon-o-sparkles')
                    ->visible(fn () => optional(auth()->user())->can('bank_statements.automatch') ?? false)
                    ->action(function (BankStatement $record) {
                        try {
                            $svc = app(\App\Services\BankReconciliationService::class);
                            $count = $svc->autoMatch($record);
                            \Filament\Notifications\Notification::make()->title('Matched '.$count.' line(s)')->success()->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()->title('Auto-Match failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
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
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankStatements::route('/'),
            'create' => Pages\CreateBankStatement::route('/create'),
            'edit' => Pages\EditBankStatement::route('/{record}/edit'),
        ];
    }
}
