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

echo "--- All Meetings in October 2026 ---\n";

$meetings = DB::table('meetings')
    ->whereYear('date', 2026)
    ->whereMonth('date', 10)
    ->get();

echo "Found " . $meetings->count() . " meetings in October 2026\n";

foreach ($meetings as $meeting) {
    echo "--------------------------------------------------\n";
    echo "Meeting ID: {$meeting->id}\n";
    echo "Name: {$meeting->name}\n";
    echo "Date: {$meeting->date}\n";
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
}
