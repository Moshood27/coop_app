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

echo "--- Verification ---\n";
$meetings = DB::table('meetings')
    ->where('id', 34)
    ->get();
echo "Meeting ID 34 exists: " . ($meetings->count() > 0 ? "YES" : "NO") . "\n";

$attendance = DB::table('attendance_records')
    ->where('meeting_id', 34)
    ->get();
echo "Attendance records for Meeting 34: " . $attendance->count() . "\n";

$pendingFinesCheck = DB::table('attendance_records')
    ->where('meeting_id', 34)
    ->where('status', 'fine_pending')
    ->count();
echo "Pending fines for Meeting 34: $pendingFinesCheck\n";
