<?php

namespace App\Filament\Pages;

use App\Services\AccountingReportService;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletTransactionsBranchReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Financial Reports';
    protected static ?string $navigationLabel = 'Wallet Transactions Branch Report';
    protected static ?int $navigationSort = 18;

    protected static string $view = 'filament.pages.wallet-transactions-branch-report';

    public function getSubheading(): ?string
    {
        return 'Wallet transaction summaries across members grouped by branch.';
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_any_wallet_transaction');
    }

    public array $report = [
        'branches' => [],
        'grand_total_credits' => 0,
        'grand_total_debits' => 0,
        'grand_total_net' => 0,
        'grand_total_members_count' => 0,
    ];

    public ?int $branchId = null;
    public ?string $from = null;
    public ?string $to = null;

    public function mount(): void
    {
        $this->to = now()->toDateString();
        $user = auth()->user();
        if (!$user->hasRole('super_admin')) {
            $this->branchId = $user->branch_id;
        }
        $this->refreshReport();
    }

    public function updated($name): void
    {
        if (in_array($name, ['branchId', 'from', 'to'])) {
            $this->refreshReport();
        }
    }

    public function refreshReport(): void
    {
        /** @var AccountingReportService $svc */
        $svc = app(AccountingReportService::class);
        $user = auth()->user();

        $targetBranchId = $user->hasRole('super_admin') ? $this->branchId : $user->branch_id;
        $this->report = $svc->buildBranchWalletTransactionsReport($targetBranchId, $this->from, $this->to);
    }

    public function exportCsv(): StreamedResponse
    {
        $data = $this->report;
        $headers = [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
            'Content-Disposition' => 'attachment; filename="wallet-transactions-branch-report.csv"',
        ];

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Branch', 'Member', 'Membership #', 'Credits', 'Debits', 'Net', 'Transactions', 'Last Activity']);

            foreach ($data['branches'] as $branch) {
                foreach ($branch['members'] as $member) {
                    fputcsv($out, [
                        $branch['branch_name'],
                        $member['member_name'],
                        $member['membership_number'],
                        number_format($member['credits'], 2, '.', ''),
                        number_format($member['debits'], 2, '.', ''),
                        number_format($member['net'], 2, '.', ''),
                        $member['transaction_count'],
                        $member['last_transaction_date'] instanceof \Carbon\Carbon ? $member['last_transaction_date']->format('d-m-Y') : ($member['last_transaction_date'] ?? 'N/A'),
                    ]);
                }
                // Branch total
                fputcsv($out, [
                    $branch['branch_name'] . ' TOTAL',
                    '',
                    '',
                    number_format($branch['total_credits'], 2, '.', ''),
                    number_format($branch['total_debits'], 2, '.', ''),
                    number_format($branch['total_net'], 2, '.', ''),
                    '',
                    '',
                ]);
                fputcsv($out, []);
            }

            // Grand total
            if (count($data['branches']) > 1) {
                fputcsv($out, [
                    'GRAND TOTAL',
                    $data['grand_total_members_count'] . ' members',
                    '',
                    number_format($data['grand_total_credits'], 2, '.', ''),
                    number_format($data['grand_total_debits'], 2, '.', ''),
                    number_format($data['grand_total_net'], 2, '.', ''),
                    '',
                    '',
                ]);
            }

            fclose($out);
        }, 'wallet-transactions-branch-report.csv', $headers);
    }
}
