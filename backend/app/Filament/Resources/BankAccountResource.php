<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankAccountResource\Pages;
use App\Models\BankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Bank Accounts';
    protected static ?int $navigationSort = 60;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return Schema::hasTable((new BankAccount())->getTable());
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        try {
            if (! Schema::hasTable((new BankAccount())->getTable())) {
                // Prevent runtime errors before migrations are applied
                return BankAccount::query()->whereRaw('1 = 0');
            }
        } catch (\Throwable $e) {
            return BankAccount::query()->whereRaw('1 = 0');
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return optional(auth()->user())->can('bank_accounts.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return optional(auth()->user())->can('bank_accounts.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return optional(auth()->user())->can('bank_accounts.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return optional(auth()->user())->can('bank_accounts.manage') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('account_number')
                    ->label('Account Number')
                    ->maxLength(100),
                Forms\Components\Select::make('ledger_account_id')
                    ->label('Ledger Account')
                    ->relationship('ledgerAccount', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('account_number')->label('Account #')->sortable()->searchable(),
                TextColumn::make('branch.name')->label('Branch')->toggleable(),
                TextColumn::make('ledgerAccount.code')->label('GL Code')->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
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
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }
}
