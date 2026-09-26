<?php

namespace App\Services;

/**
 * Gateway service that delegates accounting reports to specialized services.
 * This maintain backward compatibility while improving modularity.
 */
class AccountingReportService
{
    protected $financialStatementService;
    protected $memberReportService;
    protected $branchReportService;
    protected $specializedReportService;

    public function __construct(
        FinancialStatementService $financialStatementService,
        MemberReportService $memberReportService,
        BranchReportService $branchReportService,
        SpecializedReportService $specializedReportService
    ) {
        $this->financialStatementService = $financialStatementService;
        $this->memberReportService = $memberReportService;
        $this->branchReportService = $branchReportService;
        $this->specializedReportService = $specializedReportService;
    }
    protected function getLedgerBalances(?string $from = null, ?string $to = null): \Illuminate\Support\Collection
    {
        return $this->financialStatementService->getLedgerBalances($from, $to);
    }

    public function buildTrialBalance(?string $from = null, ?string $to = null, float $goldPrice = 0.0): array
    {
        return $this->financialStatementService->buildTrialBalance($from, $to, $goldPrice);
    }

    public function buildBalanceSheet(?string $asOf = null, float $goldPrice = 0.0): array
    {
        return $this->financialStatementService->buildBalanceSheet($asOf, $goldPrice);
    }

    public function buildStatementOfCashFlows(?string $from = null, ?string $to = null): array
    {
        return $this->financialStatementService->buildStatementOfCashFlows($from, $to);
    }

    public function buildIncomeAndExpenditure(?string $from = null, ?string $to = null): array
    {
        return $this->financialStatementService->buildIncomeAndExpenditure($from, $to);
    }

    public function buildAppropriationAccount(?string $from = null, ?string $to = null): array
    {
        return $this->financialStatementService->buildAppropriationAccount($from, $to);
    }

    public function buildMemberSavingsLedger(int $userId): array
    {
        return $this->memberReportService->buildMemberSavingsLedger($userId);
    }

    public function buildLoanAgingReport(): array
    {
        return $this->memberReportService->buildLoanAgingReport();
    }

    public function buildMemberZakatPortfolio(?string $from = null, ?string $to = null): array
    {
        return $this->memberReportService->buildMemberZakatPortfolio($from, $to);
    }

    public function buildLoanAnalysisReport(?int $branchId = null, ?string $dateStr = null, ?string $search = null): array
    {
        return $this->memberReportService->buildLoanAnalysisReport($branchId, $dateStr, $search);
    }

    public function buildMemberLoanAnalysisReport(\App\Models\User $user, ?string $dateStr = null): array
    {
        return $this->memberReportService->buildMemberLoanAnalysisReport($user, $dateStr);
    }

    protected function generateLoanAnalysisData(Carbon $toDate, ?int $branchId = null, ?string $search = null, ?int $userId = null): array
    {
        return $this->memberReportService->generateLoanAnalysisData($toDate, $branchId, $search, $userId);
    }

    public function buildBranchQardHasanReport(?int $branchId = null, ?string $from = null, ?string $to = null, bool $onlyDefaulted = false): array
    {
        return $this->branchReportService->buildBranchQardHasanReport($branchId, $from, $to, $onlyDefaulted);
    }

    public function buildBranchContributionReport(?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        return $this->branchReportService->buildBranchContributionReport($branchId, $from, $to);
    }

    public function buildBranchWalletTransactionsReport(?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        return $this->branchReportService->buildBranchWalletTransactionsReport($branchId, $from, $to);
    }

    public function buildBranchMemberBalancesReport(?int $branchId = null, float $goldPrice = 0.0): array
    {
        return $this->branchReportService->buildBranchMemberBalancesReport($branchId, $goldPrice);
    }

    public function buildUsersByBranchReport(?int $branchId = null): array
    {
        return $this->branchReportService->buildUsersByBranchReport($branchId);
    }

    public function buildBranchSchemeReport(?int $branchId = null, ?string $from = null, ?string $to = null): array
    {
        return $this->branchReportService->buildBranchSchemeReport($branchId, $from, $to);
    }

    public function buildAdministrativeChargeReport(
        ?int $branchId = null,
        ?string $from = null,
        ?string $to = null,
        string $sortField = 'member_name',
        string $sortDirection = 'asc'
    ): array
    {
        return $this->branchReportService->buildAdministrativeChargeReport($branchId, $from, $to, $sortField, $sortDirection);
    }

    public function buildZakatReport(float $goldPrice): array
    {
        return $this->specializedReportService->buildZakatReport($goldPrice);
    }

    public function buildCharityFundReport(?string $from = null, ?string $to = null): array
    {
        return $this->specializedReportService->buildCharityFundReport($from, $to);
    }

    public function buildProjectRoiReport(): array
    {
        return $this->specializedReportService->buildProjectRoiReport();
    }

    public function buildProjectDistributionReport(int $projectId): array
    {
        return $this->specializedReportService->buildProjectDistributionReport($projectId);
    }

    public function buildTakafulPoolReport(): array
    {
        return $this->specializedReportService->buildTakafulPoolReport();
    }

    public function buildGoldSavingsReport(float $goldPrice): array
    {
        return $this->specializedReportService->buildGoldSavingsReport($goldPrice);
    }

    public function buildVendorSettlementReport(): array
    {
        return $this->specializedReportService->buildVendorSettlementReport();
    }

    public function buildAttendanceReport(?string $from = null, ?string $to = null): array
    {
        return $this->specializedReportService->buildAttendanceReport($from, $to);
    }

    public function buildShariaAuditReport(?string $from = null, ?string $to = null): array
    {
        return $this->specializedReportService->buildShariaAuditReport($from, $to);
    }

}
