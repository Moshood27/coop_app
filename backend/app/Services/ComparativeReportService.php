<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class ComparativeReportService
{
    public function incomeAndExpenditureComparative(string $fromA, string $toA, string $fromB, string $toB): array
    {
        // Fail open if reports depend on optional tables: upstream service already guards internally
        $svc = app(AccountingReportService::class);
        $a = $svc->buildIncomeAndExpenditure($fromA, $toA);
        $b = $svc->buildIncomeAndExpenditure($fromB, $toB);
        return [
            'a' => ['from' => $fromA, 'to' => $toA, 'report' => $a],
            'b' => ['from' => $fromB, 'to' => $toB, 'report' => $b],
        ];
    }

    public function balanceSheetComparative(string $asOfA, string $asOfB): array
    {
        $svc = app(AccountingReportService::class);
        $a = $svc->buildBalanceSheet($asOfA);
        $b = $svc->buildBalanceSheet($asOfB);
        return [
            'a' => ['as_of' => $asOfA, 'report' => $a],
            'b' => ['as_of' => $asOfB, 'report' => $b],
        ];
    }

    public function toCsv(array $headers, array $rows): string
    {
        $f = fopen('php://temp', 'r+');
        fputcsv($f, $headers);
        foreach ($rows as $r) {
            fputcsv($f, $r);
        }
        rewind($f);
        $csv = stream_get_contents($f);
        fclose($f);
        return (string) $csv;
    }
}
