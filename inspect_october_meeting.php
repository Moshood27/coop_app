<?php

use App\Models\Meeting;
use App\Models\AttendanceRecord;
use App\Models\WalletTransaction;
use App\Models\CharityEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.connections.mysql.host' => '127.0.0.1']);
config(['database.connections.mysql.port' => '3306']);
config(['database.connections.mysql.username' => 'sail_attaqwa']);
config(['database.connections.mysql.password' => 'pass_attaqwa']);

$meetingName = 'At-taqwa Islamic Cooperative October 4, 2026';

$meetings = Meeting::where('name', $meetingName)->get();

echo "Found " . $meetings->count() . " meetings with name: '$meetingName'\n";

foreach ($meetings as $meeting) {
    echo "\nMeeting ID: " . $meeting->id . "\n";
    echo "Date: " . $meeting->date->format('Y-m-d') . "\n";
    echo "Status: " . $meeting->status . "\n";
    echo "Fine Amount: " . $meeting->fine_amount . "\n";
    echo "Lateness Fine Amount: " . $meeting->apology_fine_amount . "\n";

    $attendanceCount = AttendanceRecord::where('meeting_id', $meeting->id)->count();
    $finePendingCount = AttendanceRecord::where('meeting_id', $meeting->id)->where('status', 'fine_pending')->count();
    $finePaidCount = AttendanceRecord::where('meeting_id', $meeting->id)->where('status', 'fine_paid')->count();
    $latenessFinePaidCount = AttendanceRecord::where('meeting_id', $meeting->id)->where('lateness_fine_paid', true)->count();

    echo "Attendance Records: $attendanceCount\n";
    echo "Fine Pending: $finePendingCount\n";
    echo "Fine Paid: $finePaidCount\n";
    echo "Lateness Fine Paid: $latenessFinePaidCount\n";

    $transactions = WalletTransaction::where('meta->meeting_id', $meeting->id)->get();
    echo "Wallet Transactions: " . $transactions->count() . "\n";
    $totalDebited = $transactions->where('type', 'debit')->sum('amount');
    echo "Total Debited: " . $totalDebited . "\n";

    $charityEntries = CharityEntry::where('note', 'like', "%(ID: {$meeting->id})%")->get();
    echo "Charity Entries: " . $charityEntries->count() . "\n";
}
