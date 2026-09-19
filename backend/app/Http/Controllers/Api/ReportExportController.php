<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountingReportService;
use App\Services\ComparativeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ReportExportController extends Controller
{
    protected function csvResponse(string $filename, string $csv)
    {
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function trialBalanceCsv(Request $request, AccountingReportService $svc)
    {
        $this->authorize('accounting.export_reports');
        // Build simple TB via existing service (guarded internally)
        $from = $request->query('from');
        $to = $request->query('to');
        $tb = $svc->buildTrialBalance($from, $to);

        // Normalized rows
        $rows = [];
        foreach ($tb as $name => $vals) {
            $rows[] = [$name, number_format((float)($vals['debit'] ?? 0), 2), number_format((float)($vals['credit'] ?? 0), 2)];
        }
        $csv = app(ComparativeReportService::class)->toCsv(['Account', 'Debit', 'Credit'], $rows);
        return $this->csvResponse('trial-balance.csv', $csv);
    }

    public function incomeExpenditureCsv(Request $request, AccountingReportService $svc)
    {
        $this->authorize('accounting.export_reports');
        $from = $request->query('from');
        $to = $request->query('to');
        $ie = $svc->buildIncomeAndExpenditure($from, $to);
        $rows = [];
        $rows[] = ['Income', 'Amount'];
        foreach ($ie['income'] as $line) {
            $rows[] = [$line['name'], number_format((float)$line['amount'], 2)];
        }
        $rows[] = ['Total Income', number_format((float)($ie['total_income'] ?? array_sum(array_column($ie['income'], 'amount'))), 2)];
        $rows[] = [];
        $rows[] = ['Expenses', 'Amount'];
        foreach ($ie['expenses'] as $line) {
            $rows[] = [$line['name'], number_format((float)$line['amount'], 2)];
        }
        $rows[] = ['Total Expenses', number_format((float)($ie['total_expenses'] ?? array_sum(array_column($ie['expenses'], 'amount'))), 2)];
        $rows[] = [];
        $rows[] = ['Surplus/(Deficit)', number_format((float)($ie['surplus'] ?? (($ie['total_income'] ?? 0) - ($ie['total_expenses'] ?? 0))), 2)];

        $csv = app(ComparativeReportService::class)->toCsv(['Label', 'Amount'], $rows);
        return $this->csvResponse('income-expenditure.csv', $csv);
    }

    public function balanceSheetCsv(Request $request, AccountingReportService $svc)
    {
        $this->authorize('accounting.export_reports');
        $asOf = $request->query('as_of') ?: now()->toDateString();
        $bs = $svc->buildBalanceSheet($asOf);
        $rows = [];
        $rows[] = ['Assets', 'Amount'];
        foreach (($bs['assets']['items'] ?? []) as $line) {
            $rows[] = [$line['name'], number_format((float)$line['amount'], 2)];
        }
        $rows[] = ['Total Assets', number_format((float)($bs['assets']['total'] ?? 0), 2)];
        $rows[] = [];
        $rows[] = ['Liabilities', 'Amount'];
        foreach (($bs['liabilities']['items'] ?? []) as $line) {
            $rows[] = [$line['name'], number_format((float)$line['amount'], 2)];
        }
        $rows[] = ['Total Liabilities', number_format((float)($bs['liabilities']['total'] ?? 0), 2)];
        $rows[] = [];
        $rows[] = ['Equity', 'Amount'];
        foreach (($bs['equity']['items'] ?? []) as $line) {
            $rows[] = [$line['name'], number_format((float)$line['amount'], 2)];
        }
        $rows[] = ['Total Equity', number_format((float)($bs['equity']['total'] ?? 0), 2)];

        $csv = app(ComparativeReportService::class)->toCsv(['Label', 'Amount'], $rows);
        return $this->csvResponse('balance-sheet.csv', $csv);
    }

    public function cashFlowsCsv(Request $request, AccountingReportService $svc)
    {
        $this->authorize('accounting.export_reports');
        $from = $request->query('from');
        $to = $request->query('to');
        $cf = $svc->buildStatementOfCashFlows($from, $to);
        $rows = [];
        $rows[] = ['Operating Activities', 'Amount'];
        foreach (($cf['operating']['inflows'] ?? []) as $line) { $rows[] = [$line['name'], number_format((float)$line['amount'], 2)]; }
        foreach (($cf['operating']['outflows'] ?? []) as $line) { $rows[] = [$line['name'], number_format((float)$line['amount'], 2)]; }
        $rows[] = ['Net Operating', number_format((float)($cf['operating']['net'] ?? 0), 2)];
        $rows[] = [];
        $rows[] = ['Investing Activities', 'Amount'];
        foreach (($cf['investing']['items'] ?? []) as $line) { $rows[] = [$line['name'], number_format((float)$line['amount'], 2)]; }
        $rows[] = ['Net Investing', number_format((float)($cf['investing']['net'] ?? 0), 2)];
        $rows[] = [];
        $rows[] = ['Financing Activities', 'Amount'];
        foreach (($cf['financing']['items'] ?? []) as $line) { $rows[] = [$line['name'], number_format((float)$line['amount'], 2)]; }
        $rows[] = ['Net Financing', number_format((float)($cf['financing']['net'] ?? 0), 2)];
        $rows[] = [];
        $rows[] = ['Net Increase in Cash', number_format((float)($cf['net_increase'] ?? 0), 2)];

        $csv = app(ComparativeReportService::class)->toCsv(['Label', 'Amount'], $rows);
        return $this->csvResponse('cash-flows.csv', $csv);
    }

    public function comparative(Request $request, ComparativeReportService $svc)
    {
        $this->authorize('accounting.view_reports');
        $type = $request->query('type', 'ie');
        if ($type === 'ie') {
            $fromA = (string) $request->query('fromA');
            $toA = (string) $request->query('toA');
            $fromB = (string) $request->query('fromB');
            $toB = (string) $request->query('toB');
            return response()->json($svc->incomeAndExpenditureComparative($fromA, $toA, $fromB, $toB));
        }
        if ($type === 'bs') {
            $asOfA = (string) $request->query('asOfA');
            $asOfB = (string) $request->query('asOfB');
            return response()->json($svc->balanceSheetComparative($asOfA, $asOfB));
        }
        return response()->json(['message' => 'Unsupported comparative type'], 400);
    }
}
