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

// Parse CLI args
foreach ($argv as $arg) {
    if (strpos($arg, '--month=') === 0) {
        $month = substr($arg, 8);
    }
    if ($arg === '--execute') {
        $dryRun = false;
    }
}

echo "Target Month: $month\n";
echo "Mode: " . ($dryRun ? "DRY RUN (Simulating)" : "EXECUTE (Permanent Changes)") . "\n";
echo "Goal: Refund DUPLICATE debits only.\n\n";

// Find duplicates: same user, source, amount, and same day
$duplicates = WalletTransaction::select(
        'user_id', 'source', 'amount', 
        DB::raw('DATE(created_at) as date'),
        DB::raw('COUNT(*) as count'),
        DB::raw('MIN(id) as keep_id')
    )
    ->whereIn('source', ['attendance_fine_collection', 'admin_charge'])
    ->where('type', 'debit')
    ->where('created_at', 'like', "$month%")
    ->groupBy('user_id', 'source', 'amount', 'date')
    ->having('count', '>', 1)
    ->get();

echo "Found " . $duplicates->count() . " groups of duplicate transactions.\n";

$stats = [
    'groups' => 0,
    'total_txs_to_refund' => 0,
    'refunded_amount' => 0,
    'admin_charges_restored' => 0,
    'fines_restored' => 0,
    'attendance_records_reverted' => 0,
    'errors' => 0
];

foreach ($duplicates as $group) {
    $stats['groups']++;
    
    $toRefund = WalletTransaction::where('user_id', $group->user_id)
        ->where('source', $group->source)
        ->where('amount', $group->amount)
        ->where('type', 'debit')
        ->where(DB::raw('DATE(created_at)'), $group->date)
        ->where('id', '!=', $group->keep_id)
        ->get();

    echo "Group: User {$group->user_id}, {$group->source}, {$group->amount} on {$group->date}. Total: {$group->count}, Refunding: " . $toRefund->count() . "\n";

    foreach ($toRefund as $tx) {
        try {
            DB::transaction(function () use ($tx, $dryRun, &$stats, $month) {
                $user = User::find($tx->user_id);
                if (!$user) return;

                echo " - Refunding TX ID {$tx->id}\n";

                if ($tx->source === 'admin_charge') {
                    $contribution = Contribution::where('user_id', $user->id)
                        ->where('reference', $tx->reference)
                        ->first();
                    if (!$contribution) {
                        $contribution = Contribution::where('user_id', $user->id)
                            ->where('amount', $tx->amount)
                            ->whereBetween('created_at', [$tx->created_at->copy()->subSeconds(5), $tx->created_at->copy()->addSeconds(5)])
                            ->first();
                    }

                    if ($contribution) {
                        if (!$dryRun) $contribution->delete();
                        echo "   * Contribution deleted (ID: " . ($contribution->id ?? 'simulated') . ")\n";
                    }

                    if (!$dryRun) {
                        $user->increment('balance', $tx->amount);
                        $user->increment('admin_charge_balance', $tx->amount);
                    }
                    $stats['admin_charges_restored'] += $tx->amount;

                } elseif ($tx->source === 'attendance_fine_collection') {
                    $charity = CharityEntry::where('user_id', $user->id)
                        ->where('amount', $tx->amount)
                        ->whereBetween('created_at', [$tx->created_at->copy()->subSeconds(5), $tx->created_at->copy()->addSeconds(5)])
                        ->first();

                    if ($charity) {
                        if (!$dryRun) $charity->delete();
                        echo "   * CharityEntry deleted (ID: " . ($charity->id ?? 'simulated') . ")\n";
                    }

                    // For attendance records, duplicates usually mean the same records were marked paid multiple times.
                    // Reverting them once is enough, but wait - if we keep ONE transaction, we should NOT revert the records.
                    // Actually, if it's a duplicate transaction, it usually didn't mark *different* records, but the *same* ones again.
                    // But if we KEEP one transaction, those records SHOULD remain 'paid'.
                    // So for DUPLICATE refunds, we generally DON'T revert attendance records unless we're refunding ALL of them.
                    echo "   * Attendance records: Skipping reversal (keeping records as 'paid' for the first TX).\n";
                    
                    if (!$dryRun) {
                        $user->increment('balance', $tx->amount);
                        $user->increment('outstanding_fines', $tx->amount);
                    }
                    $stats['fines_restored'] += $tx->amount;
                }

                if (!$dryRun) {
                    WalletTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'credit',
                        'amount' => $tx->amount,
                        'reference' => 'REFUND-DUP-' . $tx->reference,
                        'source' => 'refund',
                        'meta' => [
                            'original_tx_id' => $tx->id,
                            'original_source' => $tx->source,
                            'description' => "Automated refund for duplicate {$tx->source} on {$group->date}",
                            'action' => 'duplicate_refund_fix'
                        ]
                    ]);
                }

                $stats['total_txs_to_refund']++;
                $stats['refunded_amount'] += $tx->amount;
            });
        } catch (\Exception $e) {
            echo "Error processing TX ID {$tx->id}: " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }
}

echo "\n--- Summary ---\n";
echo "Duplicate Groups: {$stats['groups']}\n";
echo "Transactions Refunded: {$stats['total_txs_to_refund']}\n";
echo "Total Amount: " . number_format($stats['refunded_amount'], 2) . "\n";
echo "Errors: {$stats['errors']}\n";
