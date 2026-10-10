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

echo "--- Detail Analysis for Meeting 34 ---\n";

$debits = DB::table('wallet_transactions')
    ->where('meta->meeting_id', 34)
    ->where('type', 'debit')
    ->get();

foreach ($debits->take(5) as $debit) {
    $user = DB::table('users')->where('id', $debit->user_id)->first();
    echo "User ID: {$user->id}, Name: {$user->name}\n";
    echo "  Debit: ID {$debit->id}, Source: {$debit->source}, Amount: {$debit->amount}\n";
    echo "  User Outstanding Fines: {$user->outstanding_fines}\n";
    
    $refund = DB::table('wallet_transactions')
        ->where('user_id', $debit->user_id)
        ->where('type', 'credit')
        ->where('meta->original_tx_id', $debit->id)
        ->first();
    
    if ($refund) {
        echo "  Refund: ID {$refund->id}, Source: {$refund->source}, Meta: " . $refund->meta . "\n";
    } else {
        echo "  NO REFUND FOUND for this Tx.\n";
    }
    
    $record = DB::table('attendance_records')
        ->where('meeting_id', 34)
        ->where('user_id', $user->id)
        ->first();
    echo "  Attendance Record Status: " . ($record->status ?? 'N/A') . "\n";
}

echo "\n--- Sample of Pending Fines ---\n";
$pending = DB::table('attendance_records')
    ->where('meeting_id', 34)
    ->where('status', 'fine_pending')
    ->take(5)
    ->get();

foreach ($pending as $p) {
    $user = DB::table('users')->where('id', $p->user_id)->first();
    echo "User ID: {$user->id}, Outstanding Fines: {$user->outstanding_fines}, Fine Amount: {$p->fine_amount}\n";
}
