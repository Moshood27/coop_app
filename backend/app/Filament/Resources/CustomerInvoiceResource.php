<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerInvoiceResource\Pages;
use App\Models\CustomerInvoice;
use App\Models\LedgerAccount;
use App\Services\LedgerService;
use App\Support\Accounting as AccountingSupport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerInvoiceResource extends Resource
{
    protected static ?string $model = CustomerInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Financial Management';
    protected static ?string $navigationLabel = 'Customer Invoices';
    protected static ?int $navigationSort = 60;

    public static function shouldRegisterNavigation(): bool
    {
        return AccountingSupport::feature('ar_ap') && AccountingSupport::tableExists('customer_invoices');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
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
                TextColumn::make('number')->searchable()->label('Invoice #'),
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable(),
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
                    ->visible(fn (CustomerInvoice $record) => $record->status === 'draft'),
                Tables\Actions\Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (CustomerInvoice $record) => in_array($record->status, ['draft', 'void']))
                    ->form([
                        Forms\Components\Select::make('ar_account_id')
                            ->label('Accounts Receivable')
                            ->options(fn () => LedgerAccount::query()->doesntHave('children')->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('revenue_account_id')
                            ->label('Revenue Account')
                            ->options(fn () => LedgerAccount::query()->doesntHave('children')->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('reference')->maxLength(255),
                        Forms\Components\Textarea::make('description')->maxLength(500),
                    ])
                    ->action(function (CustomerInvoice $record, array $data) {
                        $journal = app(LedgerService::class)->record([
                            'date' => $record->date,
                            'reference' => $data['reference'] ?? $record->number,
                            'description' => $data['description'] ?? ('Invoice '.$record->number),
                            'created_by' => auth()->id(),
                        ], [
                            [
                                'ledger_account_id' => (int) $data['ar_account_id'],
                                'debit' => (float) $record->amount,
                                'credit' => 0,
                                'description' => 'Accounts Receivable',
                            ],
                            [
                                'ledger_account_id' => (int) $data['revenue_account_id'],
                                'debit' => 0,
                                'credit' => (float) $record->amount,
                                'description' => 'Revenue',
                            ],
                        ]);
                        $record->ledger_journal_id = $journal->id;
                        $record->status = 'posted';
                        $record->save();
                        app(LedgerService::class)->postJournal($journal, auth()->id());
                    }),
                Tables\Actions\Action::make('receive')
                    ->label('Receive Payment')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (CustomerInvoice $record) => $record->status === 'posted' && AccountingSupport::tableExists('customer_receipts'))
                    ->form([
                        Forms\Components\DatePicker::make('date')->required(),
                        Forms\Components\TextInput::make('amount')->numeric()->required(),
                        Forms\Components\TextInput::make('reference')->maxLength(255),
                        Forms\Components\Select::make('bank_or_cash_account_id')
                            ->label('Cash/Bank Account')
                            ->options(fn () => LedgerAccount::query()->doesntHave('children')->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()->required(),
                        Forms\Components\Select::make('ar_account_id')
                            ->label('Accounts Receivable')
                            ->options(fn () => LedgerAccount::query()->doesntHave('children')->where('is_active', true)->pluck('name', 'id'))
                            ->searchable()->required(),
                    ])
                    ->action(function (CustomerInvoice $record, array $data) {
                        // Reuse AR controller logic inline
                        $journal = app(LedgerService::class)->record([
                            'date' => $data['date'],
                            'reference' => $data['reference'] ?? ('RCPT-'.$record->number),
                            'description' => 'Customer receipt for invoice '.$record->number,
                            'created_by' => auth()->id(),
                        ], [
                            [
                                'ledger_account_id' => (int) $data['bank_or_cash_account_id'],
                                'debit' => (float) $data['amount'],
                                'credit' => 0,
                                'description' => 'Cash/Bank',
                            ],
                            [
                                'ledger_account_id' => (int) $data['ar_account_id'],
                                'debit' => 0,
                                'credit' => (float) $data['amount'],
                                'description' => 'Accounts Receivable',
                            ],
                        ]);

                        if (AccountingSupport::tableExists('customer_receipts')) {
                            \App\Models\CustomerReceipt::create([
                                'customer_invoice_id' => $record->id,
                                'date' => $data['date'],
                                'amount' => $data['amount'],
                                'reference' => $data['reference'] ?? null,
                                'ledger_journal_id' => $journal->id,
                            ]);
                        }

                        $paid = (float) \App\Models\CustomerReceipt::where('customer_invoice_id', $record->id)->sum('amount');
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
                    ->visible(fn (CustomerInvoice $record) => in_array($record->status, ['draft','posted']))
                    ->action(function (CustomerInvoice $record) {
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
            'index' => Pages\ListCustomerInvoices::route('/'),
            'create' => Pages\CreateCustomerInvoice::route('/create'),
            'edit' => Pages\EditCustomerInvoice::route('/{record}/edit'),
        ];
    }
}

namespace App\Filament\Resources\CustomerInvoiceResource\Pages;

use App\Filament\Resources\CustomerInvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListCustomerInvoices extends ListRecords
{
    protected static string $resource = CustomerInvoiceResource::class;
}

class CreateCustomerInvoice extends CreateRecord
{
    protected static string $resource = CustomerInvoiceResource::class;
}

class EditCustomerInvoice extends EditRecord
{
    protected static string $resource = CustomerInvoiceResource::class;
}
