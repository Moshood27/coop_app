<?php

namespace App\Console\Commands;

use App\Models\Contribution;
use App\Models\LedgerJournal;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\FinancialReconciliationService;
use Illuminate\Console\Command;

class ReconcileFinancialsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'financials:reconcile {--fix : Attempt to automatically fix discrepancies} {--user= : Reconcile for a specific user ID} {--branch= : Reconcile for a specific branch ID} {--rollback : Rollback records created by the automatic sync command}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform end-to-end financial reconciliation across contributions, wallet, loans, and ledger';

    /**
     * Execute the console command.
     */
    public function handle(FinancialReconciliationService $service)
    {
        $fix = $this->option('fix');
        $userId = $this->option('user');
        $branchId = $this->option('branch');
        $rollback = $this->option('rollback');

        if ($rollback) {
            $this->info("--- Rolling Back Synced Financial Records ---");
            if ($fix) {
                $this->warn("!!! FIX MODE ENABLED - Synced records will be DELETED !!!");
            }
            $results = $service->rollbackSync($fix, $branchId);
            $this->info("Repayments deleted: " . $results['repayments_deleted']);
            $this->info("Contributions deleted: " . $results['contributions_deleted']);
            $this->info("Loans updated: " . $results['loans_updated']);
            return 0;
        }

        $this->info("--- Starting Comprehensive Financial Reconciliation ---");
        if ($fix) {
            $this->warn("!!! FIX MODE ENABLED - Changes will be written to the database !!!");
        }

        $results = $service->run($fix, $userId, $branchId);

        $this->displayReport($results);

        if (!$userId && !$branchId) {
            $this->comment("\n4. Verifying General Ledger Integrity...");
            $this->call('audit:verify-ledger');
        }

        $this->info("\n--- Financial Reconciliation Task Completed ---");
        return 0;
    }

    protected function displayReport(array $results)
    {
        // Wallet
        $this->comment("\n1. Wallet Reconciliation Results:");
        $this->line("   Missing Ledgers: " . $results['wallet']['missing_ledger']);
        $this->line("   Balance Mismatches: " . $results['wallet']['balance_mismatches']);
        if ($results['wallet']['fixed'] > 0) $this->info("   Fixed: " . $results['wallet']['fixed']);
        foreach ($results['wallet']['errors'] as $error) $this->error("   Error: $error");

        // Contributions
        $this->comment("\n2. Contribution Reconciliation Results:");
        $this->line("   Missing Ledgers: " . $results['contributions']['missing_ledger']);
        $this->line("   Scheme Mismatches: " . $results['contributions']['scheme_mismatches']);
        if ($results['contributions']['fixed'] > 0) $this->info("   Fixed: " . $results['contributions']['fixed']);
        foreach ($results['contributions']['errors'] as $error) $this->error("   Error: $error");

        // Loans
        $this->comment("\n3. Loan Reconciliation Results:");
        $this->line("   Missing Ledgers: " . $results['loans']['missing_ledger']);
        $this->line("   Missing Passbook Links: " . $results['loans']['missing_passbook']);
        $this->line("   Balance Mismatches: " . $results['loans']['balance_mismatches']);
        if ($results['loans']['fixed'] > 0) $this->info("   Fixed: " . $results['loans']['fixed']);
        foreach ($results['loans']['errors'] as $error) $this->error("   Error: $error");
    }
}
