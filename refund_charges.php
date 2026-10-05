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
use App\Models\Scheme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

$month = '2026-10';
$dryRun = true;

// Parse CLI args if any
foreach ($argv as $arg) {
    if (strpos($arg, '--month=') === 0) {
        $month = substr($arg, 8);
    }
    if ($arg === '--execute') {
        $dryRun = false;
    }
}

echo "Target Month: $month\n";
echo "Mode: " . ($dryRun ? "DRY RUN (Simulating)" : "EXECUTE (Permanent Changes)") . "\n\n";

$txs = WalletTransaction::whereIn('source', ['attendance_fine_collection', 'admin_charge'])
    ->where('type', 'debit')
    ->where('created_at', 'like', "$month%")
    ->orderBy('created_at', 'asc')
    ->get();

echo "Found " . $txs->count() . " transactions to process.\n";

$stats = [
    'processed' => 0,
    'refunded_amount' => 0,
    'admin_charges_restored' => 0,
    'fines_restored' => 0,
    'attendance_records_reverted' => 0,
    'errors' => 0
];

foreach ($txs as $tx) {
    try {
        DB::transaction(function () use ($tx, $dryRun, &$stats) {
            $user = User::find($tx->user_id);
            if (!$user) {
                echo "User not found for TX ID {$tx->id}\n";
                return;
            }

            echo "Processing TX ID {$tx->id} | User: {$user->id} | Amount: {$tx->amount} | Source: {$tx->source}\n";

            if ($tx->source === 'admin_charge') {
                // 1. Find Contribution
                $contribution = Contribution::where('user_id', $user->id)
                    ->where('reference', $tx->reference)
                    ->first();

                if (!$contribution) {
                    // Fallback search by amount and date
                    $contribution = Contribution::where('user_id', $user->id)
                        ->where('amount', $tx->amount)
                        ->where('created_at', '>=', $tx->created_at->copy()->subSeconds(5))
                        ->where('created_at', '<=', $tx->created_at->copy()->addSeconds(5))
                        ->first();
                }

                if ($contribution) {
                    if (!$dryRun) {
                        $contribution->delete();
                    }
                    echo " - Contribution deleted (ID: " . ($contribution->id ?? 'simulated') . ")\n";
                } else {
                    echo " - WARNING: Contribution not found for this admin charge.\n";
                }

                if (!$dryRun) {
                    $user->increment('balance', $tx->amount);
                    $user->increment('admin_charge_balance', $tx->amount);
                }
                $stats['admin_charges_restored'] += $tx->amount;

            } elseif ($tx->source === 'attendance_fine_collection') {
                // 1. Find CharityEntry
                $charity = CharityEntry::where('user_id', $user->id)
                    ->where('amount', $tx->amount)
                    ->where('created_at', '>=', $tx->created_at->copy()->subSeconds(5))
                    ->where('created_at', '<=', $tx->created_at->copy()->addSeconds(5))
                    ->first();

                if ($charity) {
                    if (!$dryRun) {
                        $charity->delete();
                    }
                    echo " - CharityEntry deleted (ID: " . ($charity->id ?? 'simulated') . ")\n";
                } else {
                    echo " - WARNING: CharityEntry not found for this fine collection.\n";
                }

                // 2. Revert Attendance Records
                // Look for records marked paid around this time
                $records = AttendanceRecord::where('user_id', $user->id)
                    ->where(function($q) use ($tx) {
                        $q->whereBetween('fine_paid_at', [$tx->created_at->copy()->subSeconds(2), $tx->created_at->copy()->addSeconds(2)])
                          ->orWhere(function($sq) use ($tx) {
                              $sq->where('lateness_fine_paid', true)
                                 ->whereBetween('updated_at', [$tx->created_at->copy()->subSeconds(2), $tx->created_at->copy()->addSeconds(2)]);
                          });
                    })
                    ->get();

                foreach ($records as $record) {
                    if (!$dryRun) {
                        if ($record->status === 'fine_paid') {
                            $record->update([
                                'status' => 'fine_pending',
                                'fine_paid_at' => null
                            ]);
                        }
                        if ($record->lateness_fine_paid) {
                            $record->update([
                                'lateness_fine_paid' => false
                            ]);
                        }
                    }
                    echo " - Reverted AttendanceRecord ID: {$record->id}\n";
                    $stats['attendance_records_reverted']++;
                }

                if (!$dryRun) {
                    $user->increment('balance', $tx->amount);
                    $user->increment('outstanding_fines', $tx->amount);
                }
                $stats['fines_restored'] += $tx->amount;
            }

            // Create Refund Transaction
            if (!$dryRun) {
                WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'amount' => $tx->amount,
                    'reference' => 'REFUND-' . $tx->reference,
                    'source' => 'refund',
                    'meta' => [
                        'original_tx_id' => $tx->id,
                        'original_source' => $tx->source,
                        'description' => "Automated refund for {$month} " . ($tx->source === 'admin_charge' ? 'Administrative Charge' : 'Attendance Fine'),
                        'action' => 'monthly_refund_check'
                    ]
                ]);
            }

            $stats['processed']++;
            $stats['refunded_amount'] += $tx->amount;
        });
    } catch (\Exception $e) {
        echo "Error processing TX ID {$tx->id}: " . $e->getMessage() . "\n";
        $stats['errors']++;
    }
}

echo "\n--- Summary ---\n";
echo "Processed: {$stats['processed']}\n";
echo "Total Refunded: " . number_format($stats['refunded_amount'], 2) . "\n";
echo "Admin Charges Restored: " . number_format($stats['admin_charges_restored'], 2) . "\n";
echo "Fines Restored: " . number_format($stats['fines_restored'], 2) . "\n";
echo "Attendance Records Reverted: {$stats['attendance_records_reverted']}\n";
echo "Errors: {$stats['errors']}\n";

if ($dryRun) {
    echo "\nNOTE: This was a DRY RUN. No changes were made to the database. Run with --execute to apply changes.\n";
}
