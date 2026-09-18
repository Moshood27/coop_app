<?php

namespace App\Filament\Pages;

use App\Services\AccountingReportService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivablesAging extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Financial Reports';
    protected static ?string $navigationLabel = 'Receivables Aging';
    protected static ?int $navigationSort = 41;

    protected static string $view = 'filament.pages.receivables-aging';

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('accounting.features.ar_ap') && Schema::hasTable('customer_invoices');
    }

    public ?string $as_of = null;

    public array $report = [];

    public function mount(): void
    {
        $this->as_of = now()->toDateString();
        $this->refreshReport();
    }

    public function updated($name, $value): void
    {
        if ($name === 'as_of') {
            $this->refreshReport();
        }
    }

    public function refreshReport(): void
    {
        /** @var AccountingReportService $svc */
        $svc = app(AccountingReportService::class);
        $this->report = $svc->buildReceivablesAging($this->as_of);
    }

    public function exportCsv(): StreamedResponse
    {
        $data = $this->report;
        $headers = [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-store, no-cache',
            'Content-Disposition' => 'attachment; filename="receivables-aging.csv"',
        ];
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Receivables Aging as of', $data['as_of'] ?? ''], ",", "\"", "\\");
            fputcsv($out, [], ",", "\"", "\\");
            fputcsv($out, ['Bucket', 'Amount'], ",", "\"", "\\");
            foreach (($data['buckets'] ?? []) as $bucket => $amount) {
                fputcsv($out, [$bucket, number_format((float)$amount, 2, '.', '')], ",", "\"", "\\");
            }
            fputcsv($out, ['Count', (string)($data['count'] ?? 0)], ",", "\"", "\\");
            fclose($out);
        }, 'receivables-aging.csv', $headers);
    }
}
