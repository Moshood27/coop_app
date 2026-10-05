<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AttendanceRecord;
use App\Models\User;

$userId = 1831;
$records = AttendanceRecord::where('user_id', $userId)
    ->where(function($q) {
        $q->whereNotNull('fine_paid_at')
          ->orWhere('lateness_fine_paid', true);
    })
    ->get();

foreach ($records as $r) {
    echo "ID: {$r->id}, Status: {$r->status}, Paid At: {$r->fine_paid_at}, Late Paid: " . ($r->lateness_fine_paid ? 'Yes' : 'No') . ", Meeting: {$r->meeting_id}\n";
}
