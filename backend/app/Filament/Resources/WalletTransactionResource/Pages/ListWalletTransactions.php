<?php

namespace App\Filament\Resources\WalletTransactionResource\Pages;

use App\Filament\Traits\HasWipeAction;

use App\Filament\Resources\WalletTransactionResource;
use App\Models\Scheme;
use App\Models\WalletTransaction;
use App\Exports\WalletTransactionReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListWalletTransactions extends ListRecords
{
    use HasWipeAction;

    protected static string $resource = WalletTransactionResource::class;

    public function getSubheading(): ?string
    {
        return 'Detailed ledger of all digital wallet transactions and movements.';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getWipeHeaderAction(),
            Actions\Action::make('downloadReport')
                ->label('Download Detailed Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->form([
                    DatePicker::make('from_date')
                        ->label('From Date'),
                    DatePicker::make('to_date')
                        ->label('To Date'),
                    Select::make('type')
                        ->label('Type')
                        ->options([
                            'credit' => 'Credit',
                            'debit' => 'Debit',
                        ]),
                    Select::make('source')
                        ->label('Gateway / Source')
                        ->options(function () {
                            return WalletTransaction::distinct()
                                ->whereNotNull('source')
                                ->pluck('source', 'source')
                                ->mapWithKeys(fn($s) => [$s => ucfirst(str_replace('_', ' ', $s))])
                                ->toArray();
                        }),
                    Select::make('scheme_id')
                        ->label('Passbook Record (Scheme)')
                        ->options(Scheme::pluck('name', 'id'))
                        ->searchable(),
                    Select::make('purpose')
                        ->label('Purpose')
                        ->options([
                            'deposit' => 'Contribution',
                            'loan_repayment' => 'Loan Repayment',
                            'fine' => 'Fine Payment',
                            'withdrawal' => 'Withdrawal',
                        ]),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    if ($user->hasRole('Branch Manager')) {
                        $data['branch_id'] = $user->branch_id;
                    }

                    return Excel::download(
                        new WalletTransactionReportExport($data),
                        'wallet-transactions-detailed-report-' . now()->format('Y-m-d') . '.xlsx'
                    );
                }),
            Actions\CreateAction::make(),
        ];
    }
}
