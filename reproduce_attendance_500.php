<?php

use App\Models\Meeting;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find a meeting
$meeting = Meeting::first();
if (!$meeting) {
    echo "No meeting found. Creating one...\n";
    $meeting = Meeting::create([
        'name' => 'Test Meeting',
        'date' => now(),
        'status' => 'audited',
    ]);
}

// Create a user and soft delete them
$user = User::factory()->create([
    'name' => 'Soft Deleted',
    'surname' => 'User',
]);
$userId = $user->id;
$user->delete();

// Create an attendance record for this user
$record = AttendanceRecord::create([
    'user_id' => $userId,
    'meeting_id' => $meeting->id,
    'status' => 'present',
    'attended_at' => now(),
]);

echo "Created attendance record for soft-deleted user ID: $userId\n";

// Authenticate as someone who can see reports
$admin = User::where('is_admin', true)->first();
if (!$admin) {
    $admin = User::factory()->create(['is_admin' => true]);
}
Auth::login($admin);

try {
    $controller = app(\App\Http\Controllers\Api\AttendanceController::class);
    $response = $controller->meetingReport($meeting);
    echo "Success! Response status: " . $response->getStatusCode() . "\n";
} catch (\Throwable $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
}

// Cleanup
$record->delete();
User::withTrashed()->find($userId)->forceDelete();
