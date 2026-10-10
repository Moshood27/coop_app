<?php
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Try to load autoloader
$paths = [
    __DIR__ . '/backend/vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
    '/var/www/html/backend/vendor/autoload.php',
    '/var/www/html/vendor/autoload.php',
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        require $path;
        $base = dirname(dirname($path));
        $app = require_once $base . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        break;
    }
}

if (!isset($app)) {
    die("Could not find autoloader.\n");
}

$dryRun = true; // Set to false to apply changes
$meetingIdToDelete = 34; // Set to 34 based on diagnostic

echo "--- Fix Duplicate Meetings ---\n";
echo "Dry Run: " . ($dryRun ? "YES" : "NO") . "\n";
echo "Target Meeting ID: $meetingIdToDelete\n";

DB::transaction(function () use ($meetingIdToDelete, $dryRun) {
    $meeting = DB::table('meetings')->where('id', $meetingIdToDelete)->first();
    if (!$meeting) {
        die("Meeting ID $meetingIdToDelete not found.\n");
    }

    echo "Meeting Name: {$meeting->name}\n";

    // 1. Handle Attendance Records and Fines
    $records = DB::table('attendance_records')->where('meeting_id', $meetingIdToDelete)->get();
    echo "Found " . $records->count() . " attendance records.\n";
    
    foreach ($records as $record) {
        if ($record->status === 'fine_pending') {
            echo "  User ID: {$record->user_id} - Cancelling pending fine (" . ($record->fine_amount ?? 0) . ")\n";
            if (!$dryRun) {
                DB::table('users')->where('id', $record->user_id)->decrement('outstanding_fines', $record->fine_amount ?? 0);
            }
        }
    }

    // 2. Handle Wallet Transactions (Refunds)
    $debits = DB::table('wallet_transactions')
        ->where('meta->meeting_id', $meetingIdToDelete)
        ->where('type', 'debit')
        ->get();
    
    echo "Found " . $debits->count() . " wallet debits to refund.\n";

    foreach ($debits as $debit) {
        // Check if already refunded (by us or someone else)
        $refunded = DB::table('wallet_transactions')
            ->where('user_id', $debit->user_id)
            ->where('type', 'credit')
            ->where('source', 'refund')
            ->where('meta->original_tx_id', $debit->id)
            ->exists();

        if ($refunded) {
            echo "  User ID: {$debit->user_id} - Already refunded for Tx ID: {$debit->id}\n";
        } else {
            echo "  User ID: {$debit->user_id} - Refunding " . $debit->amount . "\n";
            if (!$dryRun) {
                $now = Carbon::now();
                DB::table('wallet_transactions')->insert([
                    'user_id' => $debit->user_id,
                    'amount' => $debit->amount,
                    'type' => 'credit',
                    'source' => 'refund',
                    'reference' => "REFUND_MTG_{$meetingIdToDelete}_" . Str::random(5),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'meta' => json_encode(['original_tx_id' => $debit->id, 'meeting_id' => $meetingIdToDelete, 'reason' => 'Duplicate meeting cleanup'])
                ]);
                DB::table('users')->where('id', $debit->user_id)->increment('balance', $debit->amount);
            }
        }
    }

    // 3. Handle Charity Entries
    $charityEntries = DB::table('charity_entries')
        ->where('note', 'like', "%(ID: {$meetingIdToDelete})%")
        ->get();
    echo "Found " . $charityEntries->count() . " charity entries to remove.\n";
    if (!$dryRun && $charityEntries->count() > 0) {
        DB::table('charity_entries')->whereIn('id', $charityEntries->pluck('id'))->delete();
    }

    // 4. Delete the meeting and its attendance records
    echo "Deleting meeting and attendance records...\n";
    if (!$dryRun) {
        DB::table('attendance_records')->where('meeting_id', $meetingIdToDelete)->delete();
        DB::table('meetings')->where('id', $meetingIdToDelete)->delete();
    }
});

if ($dryRun) {
    echo "\nDRY RUN COMPLETE. No changes made. Set \$dryRun = false to apply.\n";
} else {
    echo "\nCHANGES APPLIED SUCCESSFULLY.\n";
}
