<?php

namespace App\Console\Commands;

use App\Models\Contribution;
use App\Services\PaystackService;
use App\Services\MonnifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcilePendingContributions extends Command
{
    protected $signature = 'reconcile:contributions';
    protected $description = 'Reconcile pending contributions by checking provider APIs';

    public function handle()
    {
        $this->info('Starting pending contributions reconciliation...');

        // Only check contributions created in the last 24 hours that are still pending
        // but older than 15 minutes (to allow time for webhooks)
        $processedCount = 0;

        Contribution::where('status', 'pending')
            ->where('created_at', '>=', now()->subHours(24))
            ->where('created_at', '<=', now()->subMinutes(15))
            ->chunkById(50, function ($pending) use (&$processedCount) {
                foreach ($pending as $contribution) {
                    $reference = $contribution->reference;
                    $this->line("Checking reference: {$reference}");

                    try {
                        // Determine provider based on meta or reference prefix if available
                        // For now, try Paystack first as it's the primary
                        $paystack = app(PaystackService::class);
                        $result = $paystack->verifyTransaction($reference);

                        if (isset($result['status']) && $result['status'] === true && isset($result['data']['status'])) {
                            $status = $result['data']['status'];
                            if ($status === 'success') {
                                $this->info("✅ Found success for {$reference}. Triggering fulfillment...");
                                // We could call a service here or just update.
                                // But fulfilling usually involves ledger entries, wallet updates etc.
                                // Best to trigger the same logic as webhook if possible.

                                // For safety, we can dispatch a job that handles fulfillment
                                // \App\Jobs\FulfillContribution::dispatch($contribution);

                                // If no job exists, we can log it for now or implement a basic fulfill.
                                $contribution->update(['status' => 'success', 'paid_at' => now()]);
                                $this->warn("Contribution updated to success. Manual ledger sync might be needed or wait for scheduled sync.");
                            } elseif ($status === 'failed') {
                                $contribution->update(['status' => 'failed']);
                                $this->error("❌ Transaction failed for {$reference}");
                            }
                        }
                    } catch (\Throwable $e) {
                        $this->error("Error checking {$reference}: " . $e->getMessage());
                    }
                    $processedCount++;
                }
            });

        $this->info('Reconciliation complete.');
    }
}
