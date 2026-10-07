<?php

// backend/cleanup_failed_refunds.php

// Use absolute path for the container or fallback to local __DIR__
$basePath = '/var/www/html';
if (!file_exists($basePath . '/vendor/autoload.php')) {
    $basePath = __DIR__;
}

if (!file_exists($basePath . '/vendor/autoload.php')) {
    die("Error: Could not find vendor/autoload.php. Are you in the backend directory?\n");
}

require_once $basePath . '/vendor/autoload.php';

if (!class_exists('Illuminate\Foundation\Application')) {
    die("Error: Laravel Application class not found. Check your vendor directory.\n");
}

$app = require_once $basePath . '/bootstrap/app.php';

use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Contribution;
use App\Models\CharityEntry;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dryRun = in_array('--dry-run', $argv);

if ($dryRun) {
    echo "DRY RUN MODE - No changes will be made.\n";
}

// Identify all refund transactions from the failed run (October 7th, 2026, starting at 14:00)
$startTime = '2026-10-07 14:00:00';
$refunds = WalletTransaction::where('source', 'refund')
    ->where('created_at', '>=', $startTime)
    ->get();

echo "Found " . $refunds->count() . " refund transactions to revert.\n";

// Disable auto-collection globally during cleanup
User::$global_skip_auto_collection = true;

$revertedCount = 0;
$snatchesUndone = 0;

foreach ($refunds as $refund) {
    try {
        DB::transaction(function () use ($refund, $dryRun, &$revertedCount, &$snatchesUndone) {
            $user = User::where('id', $refund->user_id)->lockForUpdate()->first();
            if (!$user) return;

            echo ($dryRun ? "[DRY RUN] " : "") . "Reverting refund {$refund->id} for user {$user->id} (Amount: {$refund->amount})\n";

            // Find and undo snatched transactions
            // These are debits for this user that happened at exactly the same time as the refund
            $snatches = WalletTransaction::where('user_id', $user->id)
                ->where('created_at', $refund->created_at)
                ->where('id', '>', $refund->id)
                ->whereIn('source', ['attendance_fine_collection', 'admin_charge'])
                ->get();

            foreach ($snatches as $snatch) {
                echo ($dryRun ? "[DRY RUN] " : "") . "  - Undo snatch {$snatch->id} (Source: {$snatch->source}, Amount: {$snatch->amount})\n";

                if (!$dryRun) {
                    // Restore debt balance
                    if ($snatch->source === 'attendance_fine_collection') {
                        $user->increment('outstanding_fines', $snatch->amount);

                        // Delete associated records
                        CharityEntry::where('user_id', $user->id)
                            ->where('amount', $snatch->amount)
                            ->where('created_at', $snatch->created_at)
                            ->delete();

                        Contribution::where('user_id', $user->id)
                            ->where('reference', $snatch->reference)
                            ->delete();

                        // Revert AttendanceRecord statuses (Absence Fines)
                        AttendanceRecord::where('user_id', $user->id)
                            ->where('status', 'fine_paid')
                            ->whereBetween('fine_paid_at', [$snatch->created_at->subSeconds(5), $snatch->created_at->addSeconds(5)])
                            ->update([
                                'status' => 'fine_pending',
                                'fine_paid_at' => null
                            ]);

                    } elseif ($snatch->source === 'admin_charge') {
                        $user->increment('admin_charge_balance', $snatch->amount);

                        Contribution::where('user_id', $user->id)
                            ->where('reference', $snatch->reference)
                            ->delete();
                    }

                    // Refund the snatched amount back to balance (since we are deleting the snatch debit)
                    $user->increment('balance', $snatch->amount);
                    $snatch->delete();
                    $snatchesUndone++;
                }
            }

            // Revert the refund itself
            if (!$dryRun) {
                // Decrement balance by the refund amount
                $user->decrement('balance', $refund->amount);
                $refund->delete();
                $revertedCount++;
            }
        });
    } catch (\Exception $e) {
        echo "Error reverting refund {$refund->id}: " . $e->getMessage() . "\n";
    }
}

// Ensure the flag is reset
User::$global_skip_auto_collection = false;

echo "----------------------------------\n";
echo "Summary:\n";
echo "Refunds processed: $revertedCount\n";
echo "Snatches undone: $snatchesUndone\n";
echo "Done.\n";
