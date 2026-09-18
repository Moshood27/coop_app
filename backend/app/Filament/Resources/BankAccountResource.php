<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankAccountResource\Pages;
use App\Models\BankAccount;
use App\Support\Accounting as AccountingSupport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Bank Accounts';
    protected static ?int $navigationSort = 62;

    public static function shouldRegisterNavigation(): bool
    {
        return AccountingSupport::feature('bank_reconciliation') && AccountingSupport::tableExists('bank_accounts');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('bank_name')->required(),
                Forms\Components\TextInput::make('account_number')->required(),
                Forms\Components\TextInput::make('currency')->maxLength(3)->default('NGN')->visible(fn () => AccountingSupport::feature('multicurrency')),
                Forms\Components\TextInput::make('gl_account_id')->numeric()->label('Linked GL Account ID'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('bank_name')->searchable(),
                TextColumn::make('account_number')->searchable(),
                TextColumn::make('currency')->visible(fn () => AccountingSupport::feature('multicurrency')),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
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

namespace App\Filament\Resources\BankAccountResource\Pages;

use App\Filament\Resources\BankAccountResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListBankAccounts extends ListRecords
{
    protected static string $resource = BankAccountResource::class;
}

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;
}

class EditBankAccount extends EditRecord
{
    protected static string $resource = BankAccountResource::class;
}
