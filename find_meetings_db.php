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
        $base = dirname(dirname($path)); // Go up from vendor to backend root
        $app = require_once $base . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        break;
    }
}

if (!isset($app)) {
    die("Could not find autoloader. Paths checked: " . implode(', ', $paths) . "\n");
}

echo "--- Duplicate Meeting Search ---\n";

$meetingNamePart = 'At-taqwa Islamic Cooperative';
$date = '2026-10-04';

$meetings = DB::table('meetings')
    ->where('name', 'like', '%' . $meetingNamePart . '%')
    ->whereDate('date', $date)
    ->get();

echo "Found " . $meetings->count() . " meetings matching '$meetingNamePart' on $date\n";

foreach ($meetings as $meeting) {
    echo "--------------------------------------------------\n";
    echo "Meeting ID: {$meeting->id}\n";
    echo "Name: {$meeting->name}\n";
    echo "Status: {$meeting->status}\n";
    echo "Fine Amount: {$meeting->fine_amount}\n";
    
    $attendance = DB::table('attendance_records')->where('meeting_id', $meeting->id)->get();
    $pendingFines = $attendance->where('status', 'fine_pending');
    $paidFines = $attendance->where('status', 'fine_paid');
    
    echo "Attendance Count: " . $attendance->count() . "\n";
    echo "Pending Fines: " . $pendingFines->count() . "\n";
    echo "Paid Fines: " . $paidFines->count() . "\n";
    
    // Check for debits in wallet_transactions
    $debits = DB::table('wallet_transactions')
        ->where('meta->meeting_id', $meeting->id)
        ->where('type', 'debit')
        ->get();
        
    echo "Wallet Debits: " . $debits->count() . " Total: " . $debits->sum('amount') . "\n";

    // Also check for debits by description if meta is missing
    $debitsByDesc = DB::table('wallet_transactions')
        ->where('description', 'like', '%' . $meeting->name . '%')
        ->where('type', 'debit')
        ->whereNotIn('id', $debits->pluck('id'))
        ->get();
    
    if ($debitsByDesc->count() > 0) {
        echo "Wallet Debits (by description): " . $debitsByDesc->count() . " Total: " . $debitsByDesc->sum('amount') . "\n";
        $debits = $debits->concat($debitsByDesc);
    }
    
    // Check for refunds
    foreach ($debits as $debit) {
        $refund = DB::table('wallet_transactions')
            ->where('user_id', $debit->user_id)
            ->where('type', 'credit')
            ->where('description', 'like', '%Refund%')
            ->where('description', 'like', '%' . $meeting->name . '%')
            ->first();
            
        if ($refund) {
             echo "  User {$debit->user_id}: Debited {$debit->amount}, Refunded {$refund->amount}\n";
        } else {
             echo "  User {$debit->user_id}: Debited {$debit->amount}, NOT REFUNDED\n";
        }
    }
}
