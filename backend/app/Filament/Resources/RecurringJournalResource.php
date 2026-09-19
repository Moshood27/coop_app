<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecurringJournalResource\Pages;
use App\Models\RecurringJournal;
use App\Models\LedgerAccount;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class RecurringJournalResource extends Resource
{
    protected static ?string $model = RecurringJournal::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Recurring Journals';
    protected static ?int $navigationSort = 75;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable((new RecurringJournal())->getTable());
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        try {
            if (! Schema::hasTable((new RecurringJournal())->getTable())) {
                return RecurringJournal::query()->whereRaw('1 = 0');
            }
        } catch (\Throwable $e) {
            return RecurringJournal::query()->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return optional(auth()->user())->can('recurring_journals.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return optional(auth()->user())->can('recurring_journals.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return optional(auth()->user())->can('recurring_journals.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return optional(auth()->user())->can('recurring_journals.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('schedule')
                    ->label('Schedule (e.g., monthly)')
                    ->placeholder('monthly'),
                Forms\Components\Toggle::make('is_active')->default(true),

                Forms\Components\Repeater::make('template')
                    ->label('Template Lines')
                    ->schema([
                        Forms\Components\Select::make('ledger_account_id')
                            ->label('Ledger Account')
                            ->options(fn () => LedgerAccount::query()->orderBy('code')->get()->mapWithKeys(fn($a) => [$a->id => $a->code.' · '.$a->name])->toArray())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('debit')->numeric()->default(0),
                        Forms\Components\TextInput::make('credit')->numeric()->default(0),
                        Forms\Components\TextInput::make('description')->maxLength(255),
                        Forms\Components\Select::make('branch_id')
                            ->label('Branch')
                            ->options(fn () => class_exists(Branch::class) ? Branch::query()->orderBy('name')->pluck('name', 'id')->toArray() : [])
                            ->searchable()
                            ->preload(),
                    ])->columns(5)->collapsible(),
                Forms\Components\Textarea::make('description')->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('schedule')->label('Schedule'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('next_run_at')->dateTime()->toggleable(),
                TextColumn::make('last_run_at')->dateTime()->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('runNow')
                    ->label('Run Now')
                    ->icon('heroicon-o-play')
                    ->visible(fn () => optional(auth()->user())->can('recurring_journals.run') ?? false)
                    ->action(function ($record) {
                        try {
                            if (Schema::hasTable('recurring_journals')) {
                                app(\App\Services\RecurringJournalService::class)->runNow($record, now());
                            }
                        } catch (\Throwable $e) {}
                    }),
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
            'index' => Pages\ListRecurringJournals::route('/'),
            'create' => Pages\CreateRecurringJournal::route('/create'),
            'edit' => Pages\EditRecurringJournal::route('/{record}/edit'),
        ];
    }
}
