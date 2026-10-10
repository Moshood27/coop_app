<?php
require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Current Dir: " . getcwd() . "\n";
echo "Script Dir (__DIR__): " . __DIR__ . "\n";

if (class_exists('App\Models\User')) {
    echo "App\Models\User exists\n";
} else {
    echo "App\Models\User DOES NOT exist\n";
}

if (class_exists('App\Models\Meeting')) {
    echo "App\Models\Meeting exists\n";
} else {
    echo "App\Models\Meeting DOES NOT exist\n";
}

$meetingFile = __DIR__ . '/backend/app/Models/Meeting.php';
echo "Meeting file path: $meetingFile\n";
echo "Meeting file exists: " . (file_exists($meetingFile) ? 'Yes' : 'No') . "\n";
