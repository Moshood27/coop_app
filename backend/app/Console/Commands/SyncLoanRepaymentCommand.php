<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Contribution;
use App\Models\Scheme;
use App\Models\QardHasan;
use App\Models\QardHasanRepayment;
use Illuminate\Support\Facades\DB;

class SyncLoanRepaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-loan-repayments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync loan repayments with passbook entries and fix missing records (Docker compatible)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $loanScheme = Scheme::where('name', 'Loan Repayment')->first();

        if (!$loanScheme) {
            $this->error("'Loan Repayment' scheme not found.");
            return 1;
        }

        $this->info("Starting Sync Process...");

        // --- PART 1: Fix existing Contributions (Wallet Allocations) ---
        $this->info("\nChecking for misconfigured Contributions (Wallet Allocations)...");

        $contributions = Contribution::where('scheme_id', $loanScheme->id)
            ->where(function($q) {
                $q->whereNull('qard_hasan_id')
                  ->orWhere('category', '!=', 'loan_repayment');
            })
            ->where('status', 'success')
            ->with('user')
            ->get();

        $this->info("Found " . $contributions->count() . " contributions to fix.");

        $fixedCount = 0;
        foreach ($contributions as $con) {
            $this->line("Processing Contribution ID: {$con->id} (Ref: {$con->reference}, User: {$con->user->full_name})");

            DB::beginTransaction();
            try {
                // 1. Ensure category is 'loan_repayment'
                if ($con->category !== 'loan_repayment') {
                    $con->category = 'loan_repayment';
                    $this->comment("  - Updated category to 'loan_repayment'");
                }

                // 2. Find associated repayment record by reference
                $repayment = QardHasanRepayment::where('reference', $con->reference)->first();

                $loanId = $con->qard_hasan_id;

                if ($repayment) {
                    $this->comment("  - Found existing repayment record (Loan ID: {$repayment->qard_hasan_id})");
                    $loanId = $repayment->qard_hasan_id;
                } else {
                    // Try to find an active loan for the user to link
                    $loan = QardHasan::where('user_id', $con->user_id)
                        ->whereIn('status', ['active', 'defaulted'])
                        ->whereColumn('paid_amount', '<', 'principal_amount')
                        ->first();

                    if (!$loan) {
                        $loan = QardHasan::where('user_id', $con->user_id)
                            ->orderByDesc('updated_at')
                            ->first();
                    }

                    if ($loan) {
                        $loanId = $loan->id;
                        $repayment = QardHasanRepayment::create([
                            'qard_hasan_id' => $loan->id,
                            'amount' => $con->amount,
                            'payment_method' => $con->payment_method ?: 'wallet',
                            'reference' => $con->reference,
                            'status' => 'success',
                            'paid_at' => $con->paid_at ?: $con->created_at,
                            'notes' => $con->notes ?: 'Repayment via Wallet (Synced)',
                        ]);
                        $this->comment("  - Created missing repayment record for Loan ID: {$loan->id}");

                        // Only update paid_amount if we just created the repayment
                        $loan->increment('paid_amount', (float)$con->amount);
                        if ($loan->paid_amount >= $loan->principal_amount) {
                            $loan->update(['status' => 'completed']);
                        }
                    }
                }

                // 3. Link contribution to loan for Passbook visibility
                if ($loanId && $con->qard_hasan_id != $loanId) {
                    $con->qard_hasan_id = $loanId;
                    $this->comment("  - Linked contribution to Loan ID: {$loanId}");
                }

                $con->save();
                DB::commit();
                $fixedCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  - Error: " . $e->getMessage());
            }
        }

        // --- PART 2: Create missing Contributions (Manual Admin Repayments) ---
        $this->info("\nChecking for Repayments without Passbook entries...");

        // Find all success repayments that don't have a matching contribution by reference
        $repayments = QardHasanRepayment::where('status', 'success')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('contributions')
                      ->whereColumn('contributions.reference', 'qard_hasan_repayments.reference');
            })
            ->with('qardHasan.user')
            ->get();

        $this->info("Found " . $repayments->count() . " repayments to sync to Passbook.");

        $syncedCount = 0;
        foreach ($repayments as $rep) {
            if (!$rep->qardHasan || !$rep->qardHasan->user) {
                $this->warn("Skipping repayment ID {$rep->id}: Loan or User not found.");
                continue;
            }

            $this->line("Syncing Repayment ID: {$rep->id} (Ref: {$rep->reference}, User: {$rep->qardHasan->user->full_name})");

            DB::beginTransaction();
            try {
                Contribution::create([
                    'user_id' => $rep->qardHasan->user_id,
                    'scheme_id' => $loanScheme->id,
                    'amount' => $rep->amount,
                    'status' => 'success',
                    'paid_at' => $rep->paid_at ?: $rep->created_at,
                    'payment_method' => $rep->payment_method ?: 'cash',
                    'reference' => $rep->reference,
                    'category' => 'loan_repayment',
                    'qard_hasan_id' => $rep->qard_hasan_id,
                    'notes' => $rep->notes ?: "Repayment for Loan QH-{$rep->qard_hasan_id} (Synced)",
                ]);

                DB::commit();
                $this->comment("  - Created Contribution record for Passbook.");
                $syncedCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  - Error: " . $e->getMessage());
            }
        }

        $this->info("\n--- Summary ---");
        $this->info("Fixed Contributions: $fixedCount");
        $this->info("Synced Repayments to Passbook: $syncedCount");
        $this->info("Sync Process Completed.");

        return 0;
    }
}
