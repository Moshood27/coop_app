<?php

namespace App\Filament\Pages;

use App\Services\AccountingReportService;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use App\Models\Branch;

class AdministrativeChargeReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Financial Reports';
    protected static ?string $navigationLabel = 'Administrative Charges Report';
    protected static ?int $navigationSort = 18;

    protected static string $view = 'filament.pages.administrative-charge-report';

    public ?int $branchId = null;
    public ?string $from = null;
    public ?string $to = null;

    public array $report = [
        'branches' => [],
        'grand_total_collected' => 0,
        'grand_total_outstanding' => 0,
        'grand_total_members_count' => 0,
    ];

    public function getSubheading(): ?string
    {
        return 'Monthly Administrative Charges (Sitting/Meeting Fees) - Collected vs Outstanding by Branch.';
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_any_user');
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole('super_admin')) {
            $this->branchId = $user->branch_id;
        }

        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->endOfMonth()->toDateString();

        $this->refreshReport();
    }

    public function refreshReport(): void
    {
        /** @var AccountingReportService $svc */
        $svc = app(AccountingReportService::class);
        $user = auth()->user();

        $targetBranchId = $user->hasRole('super_admin') ? $this->branchId : $user->branch_id;
        $this->report = $svc->buildAdministrativeChargeReport($targetBranchId, $this->from, $this->to);
    }

    public function exportCsv(): StreamedResponse
    {
        $data = $this->report;
        $headers = [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
            'Content-Disposition' => 'attachment; filename="administrative-charges-report.csv"',
        ];

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Branch', 'Member', 'Membership #', 'Type', 'Collected', 'Outstanding', 'Last Charged At'], ",", "\"", "\\");

            foreach ($data['branches'] as $branch) {
                foreach ($branch['members'] as $member) {
                    fputcsv($out, [
                        $branch['branch_name'],
                        $member['member_name'],
                        $member['membership_number'],
                        $member['is_distant'] ? 'Meeting Fee (Distant)' : 'Sitting Fee (Regular)',
                        number_format($member['collected'], 2, '.', ''),
                        number_format($member['outstanding'], 2, '.', ''),
                        $member['last_charge_date'] ?? 'N/A',
                    ], ",", "\"", "\\");
                }
                // Branch total
                fputcsv($out, [
                    $branch['branch_name'] . ' TOTAL',
                    '',
                    '',
                    '',
                    number_format($branch['total_collected'], 2, '.', ''),
                    number_format($branch['total_outstanding'], 2, '.', ''),
                    '',
                ], ",", "\"", "\\");
                fputcsv($out, [], ",", "\"", "\\");
            }

            // Grand total
            if (count($data['branches']) > 1) {
                fputcsv($out, [
                    'GRAND TOTAL',
                    $data['grand_total_members_count'] . ' members',
                    '',
                    '',
                    number_format($data['grand_total_collected'], 2, '.', ''),
                    number_format($data['grand_total_outstanding'], 2, '.', ''),
                    '',
                ], ",", "\"", "\\");
            }

            fclose($out);
        }, 'administrative-charges-report.csv', $headers);
    }
}
