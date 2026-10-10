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

echo "--- Charity Ledger Check ---\n";
$charityEntries = DB::table('charity_ledger')
    ->where('note', 'like', '%(ID: 34)%')
    ->get();
echo "Found " . $charityEntries->count() . " charity entries for Meeting 34.\n";
foreach ($charityEntries as $ce) {
    echo "  Entry ID: {$ce->id}, User ID: {$ce->user_id}, Amount: {$ce->amount}, Note: {$ce->note}\n";
}
