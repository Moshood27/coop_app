<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorBillResource\Pages;
use App\Models\VendorBill;
use App\Models\LedgerAccount;
use App\Services\LedgerService;
use App\Support\Accounting as AccountingSupport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VendorBillResource extends Resource
{
    protected static ?string $model = VendorBill::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Vendor Bills';
    protected static ?int $navigationSort = 61;

    public static function shouldRegisterNavigation(): bool
    {
        return AccountingSupport::feature('ar_ap') && AccountingSupport::tableExists('vendor_bills');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->required()
                    ->disabled(fn ($record) => $record && $record->status !== 'draft'),
                Forms\Components\TextInput::make('number')
                    ->maxLength(50)
                    ->disabled(fn ($record) => $record && $record->status !== 'draft'),
                Forms\Components\DatePicker::make('date')
                    ->required()
                    ->disabled(fn ($record) => $record && $record->status !== 'draft'),
                Forms\Components\DatePicker::make('due_date')
                    ->disabled(fn ($record) => $record && $record->status !== 'draft'),
                Forms\Components\TextInput::make('currency')
                    ->maxLength(3)
                    ->default('NGN')
                    ->disabled(fn ($record) => $record && $record->status !== 'draft')
                    ->visible(fn () => AccountingSupport::feature('multicurrency')),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->disabled(fn ($record) => $record && $record->status !== 'draft'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('number')->searchable()->label('Bill #'),
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable(),
                TextColumn::make('amount')->money('ngn', true)->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'paid',
                        'info' => 'posted',
                        'danger' => 'void',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (VendorBill $record) => $record->status === 'draft'),
                Tables\Actions\Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (VendorBill $record) => in_array($record->status, ['draft', 'void']))
                    ->form([
                        Forms\Components\Select::make('expense_account_id')
                            ->label('Expense Account')
                            ->options(fn () => LedgerAccount::query()->doesntHave('children')->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('ap_account_id')
                            ->label('Accounts Payable')
                            ->options(fn () => LedgerAccount::query()->doesntHave('children')->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('reference')->maxLength(255),
                        Forms\Components\Textarea::make('description')->maxLength(500),
                    ])
                    ->action(function (VendorBill $record, array $data) {
                        $journal = app(LedgerService::class)->record([
                            'date' => $record->date,
                            'reference' => $data['reference'] ?? $record->number,
                            'description' => $data['description'] ?? ('Vendor bill '.$record->number),
                            'created_by' => auth()->id(),
                        ], [
                            [
                                'ledger_account_id' => (int) $data['expense_account_id'],
                                'debit' => (float) $record->amount,
                                'credit' => 0,
                                'description' => 'Expense',
                            ],
                            [
                                'ledger_account_id' => (int) $data['ap_account_id'],
                                'debit' => 0,
                                'credit' => (float) $record->amount,
                                'description' => 'Accounts Payable',
                            ],
                        ]);
                        $record->ledger_journal_id = $journal->id;
                        $record->status = 'posted';
                        $record->save();
                        app(LedgerService::class)->postJournal($journal, auth()->id());
                    }),
                Tables\Actions\Action::make('pay')
                    ->label('Pay')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (VendorBill $record) => $record->status === 'posted' && AccountingSupport::tableExists('vendor_payments'))
                    ->form([
                        Forms\Components\DatePicker::make('date')->required(),
                        Forms\Components\TextInput::make('amount')->numeric()->required(),
                        Forms\Components\TextInput::make('reference')->maxLength(255),
                        Forms\Components\Select::make('bank_or_cash_account_id')
                            ->label('Cash/Bank Account')
                            ->options(fn () => LedgerAccount::query()->doesntHave('children')->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()->required(),
                        Forms\Components\Select::make('ap_account_id')
                            ->label('Accounts Payable')
                            ->options(fn () => LedgerAccount::query()->doesntHave('children')->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()->required(),
                    ])
                    ->action(function (VendorBill $record, array $data) {
                        $journal = app(LedgerService::class)->record([
                            'date' => $data['date'],
                            'reference' => $data['reference'] ?? ('PAY-'.$record->number),
                            'description' => 'Payment for vendor bill '.$record->number,
                            'created_by' => auth()->id(),
                        ], [
                            [
                                'ledger_account_id' => (int) $data['ap_account_id'],
                                'debit' => (float) $data['amount'],
                                'credit' => 0,
                                'description' => 'Accounts Payable',
                            ],
                            [
                                'ledger_account_id' => (int) $data['bank_or_cash_account_id'],
                                'debit' => 0,
                                'credit' => (float) $data['amount'],
                                'description' => 'Cash/Bank',
                            ],
                        ]);

                        if (AccountingSupport::tableExists('vendor_payments')) {
                            \App\Models\VendorPayment::create([
                                'vendor_bill_id' => $record->id,
                                'date' => $data['date'],
                                'amount' => $data['amount'],
                                'reference' => $data['reference'] ?? null,
                                'ledger_journal_id' => $journal->id,
                            ]);
                        }

                        $paid = (float) \App\Models\VendorPayment::where('vendor_bill_id', $record->id)->sum('amount');
                        if ($paid >= (float) $record->amount) {
                            $record->status = 'paid';
                            $record->save();
                        }
                        app(LedgerService::class)->postJournal($journal, auth()->id());
                    }),
                Tables\Actions\Action::make('void')
                    ->label('Void')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (VendorBill $record) => in_array($record->status, ['draft','posted']))
                    ->action(function (VendorBill $record) {
                        if ($record->status === 'paid') {
                            return; // Should not happen due to visibility check
                        }
                        $record->status = 'void';
                        $record->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorBills::route('/'),
            'create' => Pages\CreateVendorBill::route('/create'),
            'edit' => Pages\EditVendorBill::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\VendorBillResource\Pages;

use App\Filament\Resources\VendorBillResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListVendorBills extends ListRecords
{
    protected static string $resource = VendorBillResource::class;
}

class CreateVendorBill extends CreateRecord
{
    protected static string $resource = VendorBillResource::class;
}

class EditVendorBill extends EditRecord
{
    protected static string $resource = VendorBillResource::class;
}
