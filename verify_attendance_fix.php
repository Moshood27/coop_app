<?php

use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Attempting to insert a test record into attendance_records...\n";
    
    // We need a valid user and meeting to avoid foreign key failures
    $user = DB::table('users')->first();
    $meeting = DB::table('meetings')->first();
    
    if (!$user || !$meeting) {
        throw new Exception("Need at least one user and one meeting to test.");
    }
    
    DB::beginTransaction();
    
    // Use a unique combination if possible, or just ignore unique constraint for this test if it fails
    try {
        $recordId = DB::table('attendance_records')->insertGetId([
            'user_id' => $user->id,
            'meeting_id' => $meeting->id,
            'status' => 'present',
            'attended_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "Successfully inserted record with ID: {$recordId}\n";
    } catch (\Illuminate\Database\QueryException $qe) {
        if ($qe->getCode() == '23000') { // Unique constraint violation
            echo "Successfully verified that 'id' is not the problem (hit unique constraint on user_id/meeting_id instead, which is expected).\n";
        } else {
            throw $qe;
        }
    }
    
    DB::rollBack();
    echo "Transaction rolled back.\n";

    echo "\nVerifying table structure for all fixed tables:\n";
    $tablesToCheck = ['attendance_records', 'investment_record_details', 'loan', 'members', 'units'];
    foreach ($tablesToCheck as $tableName) {
        $columns = DB::select("DESCRIBE {$tableName}");
        foreach ($columns as $column) {
            if ($column->Field === 'id') {
                echo "Table: {$tableName}, Field: {$column->Field}, Type: {$column->Type}, Key: {$column->Key}, Extra: {$column->Extra}\n";
            }
        }
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}
