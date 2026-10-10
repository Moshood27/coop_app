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

echo "--- Overlap Analysis ---\n";

$meeting33Users = DB::table('attendance_records')->where('meeting_id', 33)->pluck('user_id');
$meeting34Users = DB::table('attendance_records')->where('meeting_id', 34)->pluck('user_id');

$overlap = $meeting33Users->intersect($meeting34Users);
echo "Users in both meetings: " . $overlap->count() . "\n";

$meeting33Debits = DB::table('wallet_transactions')->where('meta->meeting_id', 33)->where('type', 'debit')->pluck('user_id');
$meeting34Debits = DB::table('wallet_transactions')->where('meta->meeting_id', 34)->where('type', 'debit')->pluck('user_id');

$debitOverlap = $meeting33Debits->intersect($meeting34Debits);
echo "Users debited for both: " . $debitOverlap->count() . "\n";
foreach ($debitOverlap as $uid) {
    echo "  User ID: $uid debited twice.\n";
}

$meeting34OnlyDebits = $meeting34Debits->diff($meeting33Debits);
echo "Users debited ONLY for Meeting 34: " . $meeting34OnlyDebits->count() . "\n";
