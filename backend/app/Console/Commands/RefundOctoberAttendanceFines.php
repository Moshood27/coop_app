<?php

namespace App\Console\Commands;

use App\Models\WalletTransaction;
use App\Models\Meeting;
use App\Services\AdministrativeChargeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefundOctoberAttendanceFines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refund-october-fines {--dry-run : Only show what would be refunded}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically refund members wallet for the attendance records for October 2026 meeting schedules (excluding admin charges)';

    /**
     * Execute the console command.
     */
    public function handle(AdministrativeChargeService $service)
    {
        $dryRun = $this->option('dry-run');

        // October 2026 Meetings identified: 33 and 34
        $meetingIds = Meeting::whereYear('date', 2026)
            ->whereMonth('date', 10)
            ->pluck('id')
            ->toArray();

        if (empty($meetingIds)) {
            $this->error("No meetings found for October 2026.");
            return 1;
        }

        $this->info("Found Meetings: " . implode(', ', $meetingIds));

        // Get transactions for these meetings
        // Sources: attendance_fine, attendance_fine_collection
        // We exclude admin_charge as requested.
        $transactions = WalletTransaction::whereIn('source', ['attendance_fine', 'attendance_fine_collection'])
            ->where(function ($query) use ($meetingIds) {
                foreach ($meetingIds as $id) {
                    $query->orWhereJsonContains('meta->meeting_id', $id)
                          ->orWhereJsonContains('meta->meeting_id', (string)$id);
                }
            })
            ->get();

        // Also check if some transactions might have meeting_id in description instead of meta->meeting_id due to legacy or variations
        // But based on the previous tinker output, they seem to have meeting_id in meta.

        $count = $transactions->count();
        $this->info("Found {$count} transactions to potentially refund.");

        $refundedCount = 0;
        $totalAmount = 0;

        foreach ($transactions as $tx) {
            // Check if already refunded
            $alreadyRefunded = WalletTransaction::where('reference', 'REFUND-' . $tx->reference)->exists();
            if ($alreadyRefunded) {
                $this->line("Skipping TX {$tx->id} (Ref: {$tx->reference}) - Already refunded.");
                continue;
            }

            $totalAmount += $tx->amount;
            $refundedCount++;

            if ($dryRun) {
                $this->line("[DRY RUN] Would refund TX {$tx->id}: User {$tx->user_id}, Amount {$tx->amount}, Meeting " . ($tx->meta['meeting_id'] ?? 'unknown'));
            } else {
                try {
                    $service->refundTransaction($tx);
                    $this->info("Refunded TX {$tx->id}: User {$tx->user_id}, Amount {$tx->amount}");
                } catch (\Exception $e) {
                    $this->error("Failed to refund TX {$tx->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("----------------------------------");
        $this->info(($dryRun ? "[DRY RUN] Total to refund: " : "Total refunded: ") . $refundedCount . " transactions.");
        $this->info("Total Amount: ₦" . number_format($totalAmount, 2));

        return 0;
    }
}
