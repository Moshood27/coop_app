<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WalletTransactionResource\Pages;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Scheme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Member')
                            ->relationship('user', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                            ->searchable(['surname', 'name', 'other_names'])
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'credit' => 'Credit',
                                'debit' => 'Debit',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Net Amount')
                            ->numeric()
                            ->prefix('₦')
                            ->required()
                            ->helperText('The actual amount to be added to or removed from the user balance.'),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('meta.gross_amount')
                                    ->label('Gross Amount')
                                    ->numeric()
                                    ->prefix('₦'),
                                Forms\Components\TextInput::make('meta.maintenance_charge')
                                    ->label('Maintenance Charge')
                                    ->numeric()
                                    ->prefix('₦'),
                            ])
                            ->visible(fn (Get $get) => $get('type') === 'credit'),
                        Forms\Components\TextInput::make('reference')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->default(fn () => 'MANUAL_'.strtoupper(bin2hex(random_bytes(4)))),
                        Forms\Components\TextInput::make('source')
                            ->placeholder('e.g. manual_adjustment, bank_transfer')
                            ->maxLength(100),
                        Forms\Components\Toggle::make('withdrawable')
                            ->default(true),
                        Forms\Components\KeyValue::make('meta')
                            ->formatStateUsing(function ($state) {
                                if (!is_array($state)) {
                                    return [];
                                }

                                $user = auth()->user();
                                $isSuperAdmin = $user && $user->hasRole('super_admin');

                                $sensitive = ['bvn', 'membership_number', 'account_number', 'password'];

                                foreach ($state as $key => $value) {
                                    if (!$isSuperAdmin && in_array($key, $sensitive)) {
                                        $state[$key] = is_string($value) ? Str::mask($value, '*', 2, -2) : '*******';
                                    } elseif (is_array($value) || is_object($value)) {
                                        $state[$key] = json_encode($value);
                                    }
                                }

                                return $state;
                            })
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Time')->dateTime()->sortable(),
                TextColumn::make('user.surname')
                    ->label('Member')
                    ->formatStateUsing(fn ($record) => $record->user?->full_name ?? '-')
                    ->searchable(['surname', 'name', 'other_names'])
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'success' => 'credit',
                        'danger' => 'debit',
                    ]),
                TextColumn::make('amount')
                    ->label('Net Amount')
                    ->money('ngn', true)
                    ->sortable(),
                TextColumn::make('meta.gross_amount')
                    ->label('Gross')
                    ->money('ngn', true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('meta.maintenance_charge')
                    ->label('Fee')
                    ->money('ngn', true)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference')->searchable(),
                TextColumn::make('source')->searchable(),
                IconColumn::make('withdrawable')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                    ]),
                SelectFilter::make('user_id')
                    ->label('Member')
                    ->relationship('user', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['surname', 'name', 'other_names'])
                    ->preload(),
                SelectFilter::make('source')
                    ->label('Gateway/Source')
                    ->options(fn() => WalletTransaction::distinct()->whereNotNull('source')->pluck('source', 'source')->toArray())
                    ->searchable(),
                SelectFilter::make('scheme_id')
                    ->label('Passbook Record (Scheme)')
                    ->options(fn() => Scheme::pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) return $query;
                        $schemeId = (int) $data['value'];
                        return $query->where(function ($q) use ($schemeId) {
                            $q->whereJsonContains('meta->distribution', ['scheme_id' => $schemeId])
                              ->orWhereJsonContains('meta->distribution', ['scheme_id' => (string)$schemeId]);
                        });
                    }),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('to')->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('printReceipt')
                    ->label('Print Receipt')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn (WalletTransaction $record) => route('admin.print.wallet-receipt', $record))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('downloadReport')
                    ->label('Download Report')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (Table $table) {
                        return static::exportReport($table->getFilteredQuery());
                    }),
                Tables\Actions\Action::make('branchReport')
                    ->label('Branch Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->url(fn () => \App\Filament\Pages\WalletTransactionsBranchReport::getUrl())
                    ->color('info'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWalletTransactions::route('/'),
            'create' => Pages\CreateWalletTransaction::route('/create'),
            'edit' => Pages\EditWalletTransaction::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('view_any_wallet_transaction');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_wallet_transaction');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('update_wallet_transaction');
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('delete_wallet_transaction');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                auth()->user()->hasRole('Branch Manager'),
                fn (Builder $query) => $query->whereHas('user', fn (Builder $q) => $q->where('branch_id', auth()->user()->branch_id))
            );
    }

    public static function exportReport(Builder $query): StreamedResponse
    {
        $transactions = $query->with(['user.branch'])->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="wallet-transactions-report-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Date',
                'Member Name',
                'Membership #',
                'Branch',
                'Type',
                'Amount',
                'Gateway/Source',
                'Passbook/Scheme',
                'Category',
                'Reference'
            ]);

            foreach ($transactions as $tx) {
                if ($tx->source === 'wallet_allocation' && !empty($tx->meta['distribution'])) {
                    $schemes = Scheme::whereIn('id', collect($tx->meta['distribution'])->pluck('scheme_id'))->pluck('name', 'id');
                    foreach ($tx->meta['distribution'] as $item) {
                        fputcsv($handle, [
                            $tx->created_at->format('Y-m-d H:i:s'),
                            $tx->user?->full_name ?? '-',
                            $tx->user?->membership_number ?? '-',
                            $tx->user?->branch?->name ?? '-',
                            ucfirst((string)$tx->type),
                            number_format((float)$item['amount'], 2, '.', ''),
                            'Wallet (Allocation)',
                            $schemes[$item['scheme_id']] ?? ('Scheme #' . $item['scheme_id']),
                            ucwords(str_replace('_', ' ', (string)($item['category'] ?? 'deposit'))),
                            $tx->reference
                        ]);
                    }
                } else {
                    $source = $tx->source ?? '-';
                    if (!empty($tx->meta['channel'])) {
                        $source .= ' (' . $tx->meta['channel'] . ')';
                    }
                    fputcsv($handle, [
                        $tx->created_at->format('Y-m-d H:i:s'),
                        $tx->user?->full_name ?? '-',
                        $tx->user?->membership_number ?? '-',
                        $tx->user?->branch?->name ?? '-',
                        ucfirst((string)$tx->type),
                        number_format((float)$tx->amount, 2, '.', ''),
                        $source,
                        'Wallet',
                        $tx->type === 'credit' ? 'Wallet Top-up' : 'Miscellaneous',
                        $tx->reference
                    ]);
                }
            }

            fclose($handle);
        }, 'wallet-transactions-report-' . now()->format('Y-m-d') . '.csv', $headers);
    }
}
