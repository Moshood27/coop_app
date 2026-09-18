<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccrualScheduleResource\Pages;
use App\Models\AccrualSchedule;
use App\Models\LedgerAccount;
use App\Services\AccrualService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AccrualScheduleResource extends Resource
{
    protected static ?string $model = AccrualSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Accrual Schedules';
    protected static ?int $navigationSort = 71;

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('accounting.features.accruals_deferrals')
            && Schema::hasTable('accrual_schedules');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\DatePicker::make('start_date')->required(),
                Forms\Components\DatePicker::make('end_date'),
                Forms\Components\TextInput::make('amount_total')
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
                Forms\Components\Toggle::make('auto_reverse')->inline(false)->default(false),
                Forms\Components\Toggle::make('active')->inline(false)->default(true),
                Forms\Components\Placeholder::make('posted_until')
                    ->label('Posted Until')
                    ->content(fn ($record) => $record?->posted_until)
                    ->visible(fn () => Schema::hasColumn('accrual_schedules', 'posted_until')),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('start_date')->date()->sortable(),
                TextColumn::make('end_date')->date()->sortable(),
                TextColumn::make('amount_total')->money('ngn', true)->label('Total Amount')->sortable(),
                TextColumn::make('posted_until')
                    ->date()
                    ->label('Posted Until')
                    ->sortable()
                    ->visible(fn () => Schema::hasColumn('accrual_schedules', 'posted_until')),
                TextColumn::make('active')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('postCurrentMonth')
                    ->label('Post Current Month')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn () => (bool) config('accounting.features.accruals_deferrals') && Schema::hasTable('accrual_schedules'))
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
                        $svc->postMonthlyAccruals($period, $dry);
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
            'index' => Pages\ListAccrualSchedules::route('/'),
            'create' => Pages\CreateAccrualSchedule::route('/create'),
            'edit' => Pages\EditAccrualSchedule::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\AccrualScheduleResource\Pages;

use App\Filament\Resources\AccrualScheduleResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListAccrualSchedules extends ListRecords
{
    protected static string $resource = AccrualScheduleResource::class;
}

class CreateAccrualSchedule extends CreateRecord
{
    protected static string $resource = AccrualScheduleResource::class;
}

class EditAccrualSchedule extends EditRecord
{
    protected static string $resource = AccrualScheduleResource::class;
}
