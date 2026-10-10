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
        $base = dirname($path);
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
    echo "ID: {$meeting->id}, Name: {$meeting->name}, Status: {$meeting->status}, Fine: {$meeting->fine_amount}\n";
    
    $attendance = DB::table('attendance_records')->where('meeting_id', $meeting->id)->get();
    $pending = $attendance->where('status', 'fine_pending')->count();
    $paid = $attendance->where('status', 'fine_paid')->count();
    
    echo "  Attendance: " . $attendance->count() . " (Pending Fines: $pending, Paid Fines: $paid)\n";
    
    // Check for debits in wallet_transactions
    $debits = DB::table('wallet_transactions')
        ->where('meta->meeting_id', $meeting->id)
        ->orWhere('description', 'like', '%' . $meeting->name . '%')
        ->where('type', 'debit')
        ->get();
        
    echo "  Debits: " . $debits->count() . " Total Amount: " . $debits->sum('amount') . "\n";
    
    if ($debits->count() > 0) {
        foreach ($debits as $tx) {
            echo "    Tx ID: {$tx->id}, User: {$tx->user_id}, Amount: {$tx->amount}\n";
        }
    }
}
