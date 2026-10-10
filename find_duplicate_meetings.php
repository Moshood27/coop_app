<?php
use App\Models\Meeting;
use App\Models\AttendanceRecord;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$octoberMeetings = Meeting::whereYear('date', 2026)
    ->whereMonth('date', 10)
    ->get();

echo "Meetings in October 2026:\n";
foreach ($octoberMeetings as $meeting) {
    echo "ID: {$meeting->id}, Name: {$meeting->name}, Date: {$meeting->date}, Status: {$meeting->status}\n";
    
    $attendance = AttendanceRecord::where('meeting_id', $meeting->id)->get();
    $fines = $attendance->where('status', 'fine_pending')->count();
    $paidFines = $attendance->where('status', 'fine_paid')->count();
    
    echo "  Attendance: {$attendance->count()}, Pending Fines: {$fines}, Paid Fines: {$paidFines}\n";
    
    // Check for wallet transactions linked to this meeting
    // Fines usually have a specific meta or description
    $txs = WalletTransaction::where('meta->meeting_id', $meeting->id)
        ->orWhere('description', 'like', '%' . $meeting->name . '%')
        ->get();
        
    echo "  Related Transactions: {$txs->count()}\n";
    foreach ($txs as $tx) {
        echo "    Tx ID: {$tx->id}, User: {$tx->user_id}, Amount: {$tx->amount}, Type: {$tx->type}, Desc: {$tx->description}\n";
    }
}
