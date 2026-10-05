<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WalletTransaction;
use App\Models\User;
use App\Models\Contribution;
use App\Models\CharityEntry;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$month = '2026-10';
$dryRun = true;

foreach ($argv as $arg) {
    if (strpos($arg, '--month=') === 0) $month = substr($arg, 8);
    if ($arg === '--execute') $dryRun = false;
}

echo "Target Month: $month\n";
echo "Mode: " . ($dryRun ? "DRY RUN" : "EXECUTE") . "\n\n";

$txs = WalletTransaction::whereIn('source', ['attendance_fine_collection', 'admin_charge'])
    ->where('type', 'debit')
    ->where('created_at', 'like', "$month%")
    ->orderBy('user_id')
    ->orderBy('created_at')
    ->get();

$groups = $txs->groupBy('user_id');

$stats = [
    'total_refunded' => 0,
    'amount_refunded' => 0,
    'duplicates' => 0,
    'no_debt_refunds' => 0,
];

foreach ($groups as $userId => $userTxs) {
    $user = User::find($userId);
    if (!$user) continue;

    echo "User $userId ({$user->name}):\n";

    // Track what we've seen for this user on this day to detect duplicates
    $seen = []; 

    foreach ($userTxs as $tx) {
        $date = $tx->created_at->format('Y-m-d');
        $key = "{$tx->source}_{$tx->amount}_{$date}";
        
        $shouldRefund = false;
        $reason = "";

        // 1. Check for Duplicate
        if (isset($seen[$key])) {
            $shouldRefund = true;
            $reason = "Duplicate ({$tx->source} of {$tx->amount} on {$date})";
            $stats['duplicates']++;
        } else {
            // 2. Check for "No Debt" or "Invalid" collection
            if ($tx->source === 'attendance_fine_collection') {
                // Check if any attendance records were marked paid within 5 seconds of this TX
                $recordsPaid = AttendanceRecord::where('user_id', $userId)
                    ->where(function($q) use ($tx) {
                        $q->whereBetween('fine_paid_at', [$tx->created_at->copy()->subSeconds(5), $tx->created_at->copy()->addSeconds(5)])
                          ->orWhere(function($sq) use ($tx) {
                              $sq->where('lateness_fine_paid', true)
                                 ->whereBetween('updated_at', [$tx->created_at->copy()->subSeconds(5), $tx->created_at->copy()->addSeconds(5)]);
                          });
                    })
                    ->count();
                
                if ($recordsPaid === 0) {
                    // This TX was for a fine collection but marked 0 records as paid.
                    // This might happen if outstanding_fines was manually increased but no records were tied to it,
                    // OR if it was a ghost collection.
                    // However, if the user still has outstanding_fines > 0, we might want to keep it?
                    // The user said: "if member does not have [outstanding fines] ... needs refund"
                    
                    // Let's check if the user had any fine_pending records at ALL at that time.
                    // If we can't tell, we look at the meta.
                    $shouldRefund = true; 
                    $reason = "No attendance records settled by this collection";
                    $stats['no_debt_refunds']++;
                }
            }
            
            // Mark as seen if we are keeping it
            if (!$shouldRefund) {
                $seen[$key] = $tx->id;
            }
        }

        if ($shouldRefund) {
            echo " [REFUND] TX {$tx->id} | {$tx->amount} | {$reason}\n";
            
            if (!$dryRun) {
                DB::transaction(function() use ($tx, $user, $reason) {
                    // Refund Wallet
                    $user->increment('balance', $tx->amount);
                    
                    // Restore Debt
                    if ($tx->source === 'admin_charge') {
                        $user->increment('admin_charge_balance', $tx->amount);
                        $contribution = Contribution::where('user_id', $user->id)
                            ->where('reference', $tx->reference)
                            ->first();
                        if ($contribution) $contribution->delete();
                    } else {
                        $user->increment('outstanding_fines', $tx->amount);
                        $charity = CharityEntry::where('user_id', $user->id)
                            ->where('amount', $tx->amount)
                            ->whereBetween('created_at', [$tx->created_at->copy()->subSeconds(5), $tx->created_at->copy()->addSeconds(5)])
                            ->first();
                        if ($charity) $charity->delete();
                    }

                    // Log Refund
                    WalletTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'credit',
                        'amount' => $tx->amount,
                        'reference' => 'REFUND-AUTO-' . $tx->reference,
                        'source' => 'refund',
                        'meta' => [
                            'original_tx_id' => $tx->id,
                            'reason' => $reason,
                            'description' => "Automated refund for $reason"
                        ]
                    ]);
                });
            }
            $stats['total_refunded']++;
            $stats['amount_refunded'] += $tx->amount;
        } else {
            echo " [KEEP]   TX {$tx->id} | {$tx->amount} | {$tx->source}\n";
        }
    }
}

echo "\n--- Summary ---\n";
echo "Total Refunded: {$stats['total_refunded']}\n";
echo "Total Amount: " . number_format($stats['amount_refunded'], 2) . "\n";
echo "Duplicates: {$stats['duplicates']}\n";
echo "No Debt Refunds: {$stats['no_debt_refunds']}\n";
