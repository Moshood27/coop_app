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
            Actions\ActionGroup::make([
                Actions\Action::make('downloadDetailedReport')
                    ->label('Custom Detailed Report')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->form($this->getReportForm())
                    ->action(fn(array $data) => $this->downloadReport($data)),

                Actions\Action::make('paystackCreditsReport')
                    ->label('Paystack Credits Only')
                    ->icon('heroicon-o-credit-card')
                    ->form($this->getReportForm(['source' => 'paystack']))
                    ->action(fn(array $data) => $this->downloadReport(array_merge($data, ['source' => 'paystack']))),

                Actions\Action::make('passbookAllocationsReport')
                    ->label('Passbook Allocations')
                    ->icon('heroicon-o-book-open')
                    ->form($this->getReportForm(['purpose' => 'deposit']))
                    ->action(fn(array $data) => $this->downloadReport(array_merge($data, ['purpose' => 'deposit']))),

                Actions\Action::make('loanRepaymentsReport')
                    ->label('Loan Repayment Allocations')
                    ->icon('heroicon-o-banknotes')
                    ->form($this->getReportForm(['purpose' => 'loan_repayment']))
                    ->action(fn(array $data) => $this->downloadReport(array_merge($data, ['purpose' => 'loan_repayment']))),
            ])
            ->label('Download Reports')
            ->icon('heroicon-o-arrow-down-tray')
            ->button()
            ->color('success'),

            Actions\Action::make('printReport')
                ->label('Print PDF Report')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->form($this->getReportForm(['format' => 'pdf']))
                ->action(fn(array $data) => $this->downloadReport(array_merge($data, ['format' => 'pdf']))),

            Actions\CreateAction::make(),
        ];
    }

    protected function getReportForm(array $defaults = []): array
    {
        return [
            DatePicker::make('from_date')
                ->label('From Date')
                ->default($defaults['from_date'] ?? null),
            DatePicker::make('to_date')
                ->label('To Date')
                ->default($defaults['to_date'] ?? null),
            Select::make('type')
                ->label('Type')
                ->options([
                    'credit' => 'Credit',
                    'debit' => 'Debit',
                ])
                ->default($defaults['type'] ?? null),
            Select::make('source')
                ->label('Gateway / Source')
                ->options(function () {
                    return WalletTransaction::distinct()
                        ->whereNotNull('source')
                        ->pluck('source', 'source')
                        ->mapWithKeys(fn($s) => [$s => ucfirst(str_replace('_', ' ', $s))])
                        ->toArray();
                })
                ->default($defaults['source'] ?? null),
            Select::make('branch_id')
                ->label('Branch')
                ->options(Branch::pluck('name', 'id'))
                ->visible(fn() => auth()->user()->hasRole('super_admin'))
                ->searchable()
                ->default($defaults['branch_id'] ?? null),
            Select::make('scheme_id')
                ->label('Passbook Record (Scheme)')
                ->options(Scheme::pluck('name', 'id'))
                ->searchable()
                ->default($defaults['scheme_id'] ?? null),
            Select::make('purpose')
                ->label('Purpose')
                ->options([
                    'deposit' => 'Contribution',
                    'loan_repayment' => 'Loan Repayment',
                    'fine' => 'Fine Payment',
                    'withdrawal' => 'Withdrawal',
                ])
                ->default($defaults['purpose'] ?? null),
            Select::make('format')
                ->label('Format')
                ->options([
                    'pdf' => 'PDF (.pdf)',
                    'xlsx' => 'Excel (.xlsx)',
                ])
                ->default($defaults['format'] ?? 'xlsx')
                ->required(),
            Toggle::make('sort_by_branch')
                ->label('Sort by Branch (Like Branch Report)')
                ->default($defaults['sort_by_branch'] ?? false),
        ];
    }

    protected function downloadReport(array $data)
    {
        $user = auth()->user();
        if ($user->hasRole('Branch Manager')) {
            $data['branch_id'] = $user->branch_id;
        }

        $filename = 'wallet-transactions-report-' . now()->format('Y-m-d');
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
    }
}
