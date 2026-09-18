<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankStatementResource\Pages;
use App\Models\BankAccount;
use App\Models\BankStatement;
use App\Services\BankReconciliationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class BankStatementResource extends Resource
{
    protected static ?string $model = BankStatement::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Bank Statements';
    protected static ?int $navigationSort = 61;

    public static function shouldRegisterNavigation(): bool
    {
        return config('accounting.features.bank_reconciliation')
            && Schema::hasTable('bank_statements');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Minimal form; main interactions are via header actions
                Forms\Components\Select::make('bank_account_id')
                    ->label('Bank Account')
                    ->relationship('bankAccount', 'name', fn (Builder $q) => $q->where('is_active', true))
                    ->required(),
                Forms\Components\DatePicker::make('statement_date')->required(),
                Forms\Components\TextInput::make('reference')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('bankAccount.name')->label('Account')->sortable()->searchable(),
                TextColumn::make('statement_date')->date()->sortable(),
                TextColumn::make('source')->badge(),
                TextColumn::make('reference')->searchable(),
                TextColumn::make('lines_count')
                    ->label('Lines')
                    ->counts('lines')
                    ->badge(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('importCsv')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn () => app(BankReconciliationService::class)->featureEnabled())
                    ->form([
                        Forms\Components\Select::make('bank_account_id')
                            ->label('Bank Account')
                            ->options(fn () => BankAccount::query()->where('is_active', true)->pluck('name', 'id')->toArray())
                            ->required(),
                        Forms\Components\FileUpload::make('csv')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'text/comma-separated-values'])
                            ->disk('local')
                            ->directory('imports/bank')
                            ->required(),
                        Forms\Components\TextInput::make('date_format')->default('Y-m-d')->label('Date Format'),
                        Forms\Components\TextInput::make('delimiter')->default(',')->label('Delimiter'),
                    ])
                    ->action(function (array $data) {
                        $svc = app(BankReconciliationService::class);
                        $account = BankAccount::query()->find((int)$data['bank_account_id']);
                        if (!$account) {
                            throw new \RuntimeException('Bank account not found.');
                        }
                        $relative = (string)$data['csv'];
                        $path = Storage::disk('local')->path($relative);
                        $svc->importCsv($account, $path, [
                            'date_format' => (string)($data['date_format'] ?? 'Y-m-d'),
                            'delimiter' => (string)($data['delimiter'] ?? ','),
                        ]);
                    })
            ])
            ->filters([])
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
            'index' => Pages\ListBankStatements::route('/'),
            'create' => Pages\CreateBankStatement::route('/create'),
            'edit' => Pages\EditBankStatement::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\BankStatementResource\Pages;

use App\Filament\Resources\BankStatementResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListBankStatements extends ListRecords
{
    protected static string $resource = BankStatementResource::class;
}

class CreateBankStatement extends CreateRecord
{
    protected static string $resource = BankStatementResource::class;
}

class EditBankStatement extends EditRecord
{
    protected static string $resource = BankStatementResource::class;
}
