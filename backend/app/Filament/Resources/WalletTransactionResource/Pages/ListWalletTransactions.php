<?php

namespace App\Filament\Resources\WalletTransactionResource\Pages;

use App\Filament\Traits\HasWipeAction;

use App\Filament\Resources\WalletTransactionResource;
use App\Models\Scheme;
use App\Models\Branch;
use App\Models\WalletTransaction;
use App\Exports\WalletTransactionReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
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
            Actions\Action::make('downloadDetailedReport')
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
                    Select::make('branch_id')
                        ->label('Branch')
                        ->options(Branch::pluck('name', 'id'))
                        ->visible(fn() => auth()->user()->hasRole('super_admin'))
                        ->searchable(),
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
                    Select::make('format')
                        ->label('Format')
                        ->options([
                            'pdf' => 'PDF (.pdf)',
                            'xlsx' => 'Excel (.xlsx)',
                        ])
                        ->default('pdf')
                        ->required(),
                    Toggle::make('sort_by_branch')
                        ->label('Sort by Branch (Like Branch Report)')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    if ($user->hasRole('Branch Manager')) {
                        $data['branch_id'] = $user->branch_id;
                    }

                    $filename = 'wallet-transactions-detailed-report-' . now()->format('Y-m-d');
                    $format = $data['format'] ?? 'xlsx';

                    if ($format === 'pdf') {
                        return Excel::download(
                            new WalletTransactionReportExport($data),
                            $filename . '.pdf',
                            \Maatwebsite\Excel\Excel::DOMPDF
                        );
                    }

                    return Excel::download(
                        new WalletTransactionReportExport($data),
                        $filename . '.xlsx'
                    );
                }),
            Actions\CreateAction::make(),
        ];
    }
}
