<?php

use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Contribution;
use App\Models\CharityEntry;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\DB;

// Use full path to avoid issues
$basePath = __DIR__;
$app = require_once $basePath . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dryRun = in_array('--dry-run', $argv);

// Target date: October 7, 2026 (when the failed run happened)
$targetDate = '2026-10-07';

$refunds = WalletTransaction::where('source', 'refund')
    ->whereDate('created_at', $targetDate)
    ->get();

echo "Found " . $refunds->count() . " refund transactions to revert.\n";

$processedCount = 0;
$snatchCount = 0;

// Enable global protection
User::$global_skip_auto_collection = true;

foreach ($refunds as $refund) {
    try {
        DB::transaction(function () use ($refund, $dryRun, &$processedCount, &$snatchCount) {
            $user = User::find($refund->user_id);
            if (!$user) return;

            // Find snatched transactions: debits created at the same time as the refund
            $snatched = WalletTransaction::where('user_id', $refund->user_id)
                ->where('type', 'debit')
                ->where('created_at', $refund->created_at)
                ->where('id', '>', $refund->id)
                ->whereIn('source', ['attendance_fine_collection', 'admin_charge'])
                ->get();

            echo ($dryRun ? "[DRY RUN] " : "") . "Reverting refund {$refund->id} for user {$user->id} (Amount: {$refund->amount})\n";

            foreach ($snatched as $snatch) {
                echo ($dryRun ? "[DRY RUN] " : "") . "  - Undo snatch {$snatch->id} (Source: {$snatch->source}, Amount: {$snatch->amount})\n";
                
                if (!$dryRun) {
                    if ($snatch->source === 'attendance_fine_collection') {
                        CharityEntry::where('user_id', $user->id)
                            ->where('amount', $snatch->amount)
                            ->where('created_at', $snatch->created_at)
                            ->delete();

                        $user->increment('outstanding_fines', $snatch->amount);

                        AttendanceRecord::where('user_id', $user->id)
                            ->where('status', 'fine_paid')
                            ->where('fine_paid_at', $snatch->created_at)
                            ->update([
                                'status' => 'fine_pending',
                                'fine_paid_at' => null
                            ]);
                    } elseif ($snatch->source === 'admin_charge') {
                        Contribution::where('user_id', $user->id)
                            ->where('amount', $snatch->amount)
                            ->where('reference', $snatch->reference)
                            ->delete();

                        $user->increment('admin_charge_balance', $snatch->amount);
                    }

                    $user->increment('balance', $snatch->amount);
                    $snatch->delete();
                }
                $snatchCount++;
            }

            if (!$dryRun) {
                $user->decrement('balance', $refund->amount);
                $refund->delete();
            }
            $processedCount++;
        });
    } catch (\Exception $e) {
        echo "Error reverting refund {$refund->id}: " . $e->getMessage() . "\n";
    }
}

// Disable protection
User::$global_skip_auto_collection = false;

echo "----------------------------------\n";
echo "Summary:\n";
echo "Refunds processed: $processedCount\n";
echo "Snatches undone: $snatchCount\n";
echo "Done.\n";
