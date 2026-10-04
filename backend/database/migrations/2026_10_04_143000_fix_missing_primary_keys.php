<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'attendance_records' => 'BIGINT UNSIGNED',
            'investment_record_details' => 'INT',
            'loan' => 'INT',
            'members' => 'INT',
            'units' => 'INT',
        ];

        foreach ($tables as $table => $type) {
            if (Schema::hasTable($table)) {
                // Check if primary key exists
                $pk = DB::select("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
                if (empty($pk)) {
                    echo "Adding PRIMARY KEY and AUTO_INCREMENT to {$table}.id\n";

                    // Check for duplicates before applying
                    $dups = DB::table($table)->select('id', DB::raw('COUNT(*) as count'))->groupBy('id')->having('count', '>', 1)->get();
                    if ($dups->count() > 0) {
                        echo "WARNING: Duplicate IDs found in {$table}. Skipping PK addition.\n";
                        continue;
                    }

                    try {
                        DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `id` {$type} AUTO_INCREMENT PRIMARY KEY");
                    } catch (\Exception $e) {
                        echo "Error fixing {$table}: " . $e->getMessage() . "\n";
                    }
                }
            }
        }

        // Specifically for attendance_records, ensure the unique constraint on user_id and meeting_id
        if (Schema::hasTable('attendance_records')) {
            $uniqueIndex = DB::select("SHOW INDEX FROM `attendance_records` WHERE Key_name = 'attendance_records_user_id_meeting_id_unique'");
            if (empty($uniqueIndex)) {
                $dups = DB::table('attendance_records')
                    ->select('user_id', 'meeting_id', DB::raw('COUNT(*) as count'))
                    ->groupBy('user_id', 'meeting_id')
                    ->having('count', '>', 1)
                    ->get();

                if ($dups->count() === 0) {
                    Schema::table('attendance_records', function (Blueprint $table) {
                        $table->unique(['user_id', 'meeting_id']);
                    });
                } else {
                    echo "WARNING: Duplicate (user_id, meeting_id) pairs found in attendance_records. Skipping unique index.\n";
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting AUTO_INCREMENT and PRIMARY KEY is complex and usually not desired if it was fixing a broken state.
        // We will leave it as is to avoid breaking the DB again.
    }
};
