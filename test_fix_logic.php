<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$migrations = [
    '2026_04_12_215000_create_attendance_system_tables',
    '2026_04_12_215142_create_branch_meeting_table',
    '2026_04_12_215521_migrate_meeting_branches_to_pivot',
    '2026_04_12_221000_add_pending_fines_to_attendance_system',
    '2026_04_13_161737_update_attendance_system_remove_apology_add_reminder',
    '2026_04_18_110445_add_fine_pending_to_attendance_records_status',
    '2026_04_19_210953_add_device_uuid_to_attendance_records_table',
    '2026_04_19_214716_add_lateness_fine_to_attendance_records',
    '2026_04_23_120000_increase_attendance_radius',
    '2026_04_23_131305_add_grace_period_to_meetings_table',
    '2026_04_24_205451_add_pregnancy_and_excuse_to_attendance_system',
    '2026_04_25_113000_ensure_pregnancy_and_excuse_columns_are_added',
    '2026_05_06_140408_add_meeting_attendance_count_to_qard_hasans_table',
    '2026_07_01_130000_add_verified_biometrically_to_attendance_records',
    '2026_07_06_145000_add_beacon_fields_to_meetings_table',
    '2026_07_14_230000_add_marked_by_to_attendance_records',
    '2026_09_10_140000_add_fine_amount_to_attendance_records_table',
    '2026_09_20_050000_add_correction_reason_to_attendance_records',
];

foreach ($migrations as $m) {
    $path = database_path("migrations/$m.php");
    if (file_exists($path)) {
        $migration = require $path;
        try {
            $migration->up();
            echo "Ran $m\n";
        } catch (\Exception $e) {
            echo "Skipped $m: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Not found: $m\n";
    }
}
