<?php
// cleanup_failed_refunds.php

use App\Models\WalletTransaction;
use App\Models\User;
use App\Models\Contribution;
use App\Models\CharityEntry;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dryRun = in_array('--dry-run', $argv);

// Important: Set the global skip flag to avoid triggering observers during cleanup
\App\Models\User::$global_skip_auto_collection = true;

DB::transaction(function() use ($dryRun) {
    // Look for refunds created during the problematic window today
    $refunds = WalletTransaction::where('source', 'refund')
        ->where('created_at', '>=', '2026-10-07 14:00:00')
        ->get();

    echo "Found " . $refunds->count() . " refund transactions to revert.\n";

    $totalReverted = 0;
    $totalSnatchesUndone = 0;

    foreach ($refunds as $refund) {
        $user = User::find($refund->user_id);
        if (!$user) {
            echo "User not found for refund {$refund->id}\n";
            continue;
        }

        $amount = (float)$refund->amount;
        
        // Find snatched transactions triggered by this refund (debits at the same time or immediately after)
        $snatches = WalletTransaction::where('user_id', $user->id)
            ->where('created_at', $refund->created_at)
            ->where('id', '>', $refund->id)
            ->whereIn('source', ['attendance_fine_collection', 'admin_charge'])
            ->get();

        if ($dryRun) {
            echo "[DRY RUN] Reverting refund {$refund->id} for user {$user->id} (Amount: {$amount})\n";
            foreach ($snatches as $snatch) {
                echo "[DRY RUN]   - Would undo snatch {$snatch->id} (Source: {$snatch->source}, Amount: {$snatch->amount})\n";
            }
            continue;
        }

        foreach ($snatches as $snatch) {
            echo "Undoing snatch {$snatch->id} for user {$user->id} (Ref: {$snatch->reference}, Source: {$snatch->source})\n";
            
            if ($snatch->source === 'attendance_fine_collection') {
                $user->increment('outstanding_fines', (float)$snatch->amount);
                
                // Revert attendance records status if they were marked as paid during this snatch
                $records = AttendanceRecord::where('user_id', $user->id)
                    ->where('status', 'fine_paid')
                    ->whereBetween('fine_paid_at', [$snatch->created_at->subSeconds(5), $snatch->created_at->addSeconds(5)])
                    ->get();
                foreach($records as $r) {
                    echo "  - Marking attendance record {$r->id} back to fine_pending\n";
                    $r->update(['status' => 'fine_pending', 'fine_paid_at' => null]);
                }

                // Delete CharityEntry created by the snatch
                $deletedCharity = CharityEntry::where('user_id', $user->id)
                    ->where('amount', $snatch->amount)
                    ->whereBetween('created_at', [$snatch->created_at->subSeconds(5), $snatch->created_at->addSeconds(5)])
                    ->delete();
                if ($deletedCharity) echo "  - Deleted CharityEntry\n";
            } 
            elseif ($snatch->source === 'admin_charge') {
                $user->increment('admin_charge_balance', (float)$snatch->amount);
                // Delete Contribution created by the snatch
                $deletedContrib = Contribution::where('user_id', $user->id)
                    ->where('reference', $snatch->reference)
                    ->delete();
                if ($deletedContrib) echo "  - Deleted Contribution\n";
            }

            // Restore wallet balance for the snatch debit
            $user->increment('balance', (float)$snatch->amount);
            $snatch->delete();
            $totalSnatchesUndone++;
        }

        echo "Undoing refund {$refund->id} for user {$user->id}\n";
        // Remove the refund credit from balance
        $user->decrement('balance', $amount);
        $refund->delete();
        $totalReverted++;
    }

    echo "----------------------------------\n";
    echo ($dryRun ? "[DRY RUN] Summary:" : "Cleanup Summary:") . "\n";
    echo "Refunds processed: {$totalReverted}\n";
    echo "Snatches undone: {$totalSnatchesUndone}\n";
});

\App\Models\User::$global_skip_auto_collection = false;
echo "Done.\n";
