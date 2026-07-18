<?php

namespace App\Console\Commands;

use App\Models\LedgerAccount;
use App\Models\LedgerJournal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyFinancialAudit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:verify-ledger';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the integrity of the financial double-entry ledger and audit trail';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("--- Double-Entry Ledger Verification ---");

        // 1. Check for unbalanced journals (Total Debits must equal Total Credits for each journal)
        $unbalancedCount = 0;
        LedgerJournal::with('entries')->chunk(100, function ($journals) use (&$unbalancedCount) {
            foreach ($journals as $journal) {
                $debits = (float) $journal->entries->sum('debit');
                $credits = (float) $journal->entries->sum('credit');
                if (abs($debits - $credits) > 0.001) {
                    $this->error("❌ Unbalanced journal ID: {$journal->id}, Ref: {$journal->reference}, Diff: " . ($debits - $credits));
                    $unbalancedCount++;
                }
            }
        });

        if ($unbalancedCount === 0) {
            $this->info("✅ All journals are internally balanced (Debits = Credits).");
        } else {
            $this->error("❌ Found " . $unbalancedCount . " unbalanced journals!");
        }

        // 2. Global Balance Check (Total Debits across all entries must equal Total Credits)
        $totalDebits = (float) DB::table('ledger_entries')->sum('debit');
        $totalCredits = (float) DB::table('ledger_entries')->sum('credit');

        if (abs($totalDebits - $totalCredits) < 0.01) {
            $this->info("✅ Global Ledger Balance: Total Debits (₦" . number_format($totalDebits, 2) . ") = Total Credits (₦" . number_format($totalCredits, 2) . ")");
        } else {
            $this->error("❌ Global Balance Mismatch! Diff: " . ($totalDebits - $totalCredits));
        }

        // 3. Accounting Equation Check: Assets = Liabilities + Equity + (Income - Expenses)
        $assets = (float) LedgerAccount::where('type', 'asset')->get()->sum('balance');
        $liabilities = (float) LedgerAccount::where('type', 'liability')->get()->sum('balance');
        $equity = (float) LedgerAccount::where('type', 'equity')->get()->sum('balance');
        $income = (float) LedgerAccount::where('type', 'income')->get()->sum('balance');
        $expenses = (float) LedgerAccount::where('type', 'expense')->get()->sum('balance');

        // In accounting, Net Income increases Equity.
        $netIncome = $income - $expenses;
        $totalEquityAndLiabilities = $liabilities + $equity + $netIncome;

        $this->info("\n--- Financial Summary ---");
        $this->line("Total Assets: ₦" . number_format($assets, 2));
        $this->line("Total Liabilities: ₦" . number_format($liabilities, 2));
        $this->line("Total Equity: ₦" . number_format($equity, 2));
        $this->line("Net Income (Unclosed): ₦" . number_format($netIncome, 2));
        $this->line("-------------------------");
        $this->line("Total Liabilities + Equity: ₦" . number_format($totalEquityAndLiabilities, 2));

        if (abs($assets - $totalEquityAndLiabilities) < 0.01) {
            $this->info("✅ Accounting Equation Balance: Assets = Liabilities + Equity");
        } else {
            $this->error("❌ Accounting Equation Mismatch! Diff: " . ($assets - $totalEquityAndLiabilities));
            $this->line("   Note: This might happen if entries were deleted or manual adjustments were made outside the ledger system.");
        }

        // 4. Activity Log Check
        $logCount = DB::table('activity_log')->count();
        $this->info("\n--- Audit Logs ---");
        $this->info("✅ Total Activity Logs: " . $logCount);

        $shariahLogCount = DB::table('shariah_audit_logs')->count();
        $this->info("✅ Total Shariah Audit Logs: " . $shariahLogCount);

        return 0;
    }
}
