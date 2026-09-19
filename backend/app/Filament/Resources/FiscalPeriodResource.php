<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FiscalPeriodResource\Pages;
use App\Models\FiscalPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use App\Services\YearEndCloseService;

class FiscalPeriodResource extends Resource
{
    protected static ?string $model = FiscalPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Fiscal Periods';
    protected static ?int $navigationSort = 70;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable((new FiscalPeriod())->getTable());
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        try {
            if (! Schema::hasTable((new FiscalPeriod())->getTable())) {
                return FiscalPeriod::query()->whereRaw('1 = 0');
            }
        } catch (\Throwable $e) {
            return FiscalPeriod::query()->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return optional(auth()->user())->can('periods.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return optional(auth()->user())->can('periods.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return optional(auth()->user())->can('periods.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return optional(auth()->user())->can('periods.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\DatePicker::make('starts_on')->required(),
                Forms\Components\DatePicker::make('ends_on')->required(),
                Forms\Components\Toggle::make('is_closed')->label('Closed')->disabledOn('create'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('starts_on')->date()->sortable(),
                TextColumn::make('ends_on')->date()->sortable(),
                Tables\Columns\IconColumn::make('is_closed')->boolean()->label('Closed')->sortable(),
                TextColumn::make('closed_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('close')
                    ->visible(fn ($record) => (! $record->is_closed) && (optional(auth()->user())->can('periods.close') ?? false))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            if (Schema::hasTable('fiscal_periods')) {
                                $record->is_closed = true;
                                $record->closed_at = now();
                                $record->save();
                            }
                        } catch (\Throwable $e) {}
                    })
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger'),
                Tables\Actions\Action::make('open')
                    ->visible(fn ($record) => ((bool) $record->is_closed) && (optional(auth()->user())->can('periods.close') ?? false))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            if (Schema::hasTable('fiscal_periods')) {
                                $record->is_closed = false;
                                $record->closed_at = null;
                                $record->closed_by = null;
                                $record->save();
                            }
                        } catch (\Throwable $e) {}
                    })
                    ->icon('heroicon-o-lock-open')
                    ->color('success'),
                Action::make('year_end_close')
                    ->label('Year-End Close')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn($record) => (!$record->is_closed) && (optional(auth()->user())->can('ops.year_end_close') ?? false))
                    ->form([
                        Forms\Components\Toggle::make('dry_run')->default(true)->helperText('Preview only when enabled'),
                        Forms\Components\TextInput::make('retained_code')->label('Retained Earnings Code')->placeholder('3100')->maxLength(10),
                        Forms\Components\Toggle::make('close')->label('Close Period After Posting')->default(true),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            if (!Schema::hasTable('fiscal_periods')) {
                                Notification::make('yec_missing')->title('Migrations pending')->warning()->body('fiscal_periods table missing')->send();
                                return;
                            }
                            $res = app(YearEndCloseService::class)->run($record->id, auth()->id() ?? null, (bool)($data['dry_run'] ?? true), (bool)($data['close'] ?? true), $data['retained_code'] ?? null);
                            Notification::make('yec_ok')->title('Year-End Close')->success()->body('Net: ' . number_format($res['net'] ?? 0, 2) . '; Journal: ' . ($res['posted_journal_id'] ?? 'N/A'))->send();
                        } catch (\Throwable $e) {
                            Notification::make('yec_err')->title('Year-End Close Failed')->danger()->body($e->getMessage())->send();
                        }
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
            'index' => Pages\ListFiscalPeriods::route('/'),
            'create' => Pages\CreateFiscalPeriod::route('/create'),
            'edit' => Pages\EditFiscalPeriod::route('/{record}/edit'),
        ];
    }
}
