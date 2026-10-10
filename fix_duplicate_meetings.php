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
$meetingIdToDelete = null; // MUST BE SET AFTER DIAGNOSTIC

echo "--- Fix Duplicate Meetings ---\n";
echo "Dry Run: " . ($dryRun ? "YES" : "NO") . "\n";

if (!$meetingIdToDelete) {
    echo "ERROR: \$meetingIdToDelete is not set. Run find_meetings_db.php first to identify the meeting ID to delete.\n";
    exit(1);
}

DB::transaction(function () use ($meetingIdToDelete, $dryRun) {
    $meeting = DB::table('meetings')->where('id', $meetingIdToDelete)->first();
    if (!$meeting) {
        die("Meeting ID $meetingIdToDelete not found.\n");
    }

    echo "Target Meeting: {$meeting->name} (ID: {$meeting->id})\n";

    // 1. Identify attendance records and fines
    $attendance = DB::table('attendance_records')->where('meeting_id', $meetingIdToDelete)->get();
    echo "Found " . $attendance->count() . " attendance records.\n";
    
    foreach ($attendance as $record) {
        if ($record->status === 'fine_pending') {
            echo "  Cancelling pending fine for User ID: {$record->user_id}\n";
            if (!$dryRun) {
                DB::table('attendance_records')->where('id', $record->id)->delete();
                // If there's an outstanding_fines column in users table, we should decrement it?
                // Looking at User.php, it has outstanding_fines.
                // However, if the fine was never paid, decrementing might be necessary if it was already added.
                // Usually fines are added when the meeting is audited.
                DB::table('users')->where('id', $record->user_id)->decrement('outstanding_fines', $record->fine_amount ?? 0);
            }
        } elseif ($record->status === 'fine_paid') {
            echo "  User ID: {$record->user_id} already paid the fine. Should we refund?\n";
            // The issue description says "delete one with is pending fine for members" 
            // and "check if member is debited and not refunded then credit that member".
            // If they paid a fine, it's a debit.
        }
    }

    // 2. Identify wallet transactions linked to this meeting
    $debits = DB::table('wallet_transactions')
        ->where('meta->meeting_id', $meetingIdToDelete)
        ->where('type', 'debit')
        ->get();

    // Also check by description
    $debitsByDesc = DB::table('wallet_transactions')
        ->where('description', 'like', '%' . $meeting->name . '%')
        ->where('type', 'debit')
        ->whereNotIn('id', $debits->pluck('id'))
        ->get();
    $debits = $debits->concat($debitsByDesc);

    echo "Found " . $debits->count() . " debits to refund.\n";

    foreach ($debits as $debit) {
        // Check if already refunded
        $refunded = DB::table('wallet_transactions')
            ->where('user_id', $debit->user_id)
            ->where('type', 'credit')
            ->where('description', 'like', '%Refund%')
            ->where('description', 'like', '%' . $meeting->name . '%')
            ->exists();

        if ($refunded) {
            echo "  User ID: {$debit->user_id} already refunded for debit ID: {$debit->id}\n";
        } else {
            echo "  Refunding User ID: {$debit->user_id} Amount: {$debit->amount}\n";
            if (!$dryRun) {
                // Perform refund
                $now = Carbon::now();
                DB::table('wallet_transactions')->insert([
                    'user_id' => $debit->user_id,
                    'amount' => $debit->amount,
                    'type' => 'credit',
                    'source' => 'refund',
                    'description' => "Refund for duplicate meeting: {$meeting->name}",
                    'created_at' => $now,
                    'updated_at' => $now,
                    'meta' => json_encode(['original_tx_id' => $debit->id, 'meeting_id' => $meetingIdToDelete])
                ]);
                
                // Update user wallet balance if applicable
                DB::table('users')->where('id', $debit->user_id)->increment('balance', $debit->amount);
            }
        }
    }

    // 3. Delete the meeting
    echo "Deleting meeting ID: $meetingIdToDelete\n";
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
