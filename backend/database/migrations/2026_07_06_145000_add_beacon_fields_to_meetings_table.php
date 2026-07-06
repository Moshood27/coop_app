<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('beacon_uuid')->nullable()->after('pin');
            $table->integer('beacon_major')->nullable()->after('beacon_uuid');
            $table->integer('beacon_minor')->nullable()->after('beacon_major');
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->boolean('verified_via_beacon')->default(false)->after('verified_biometrically');
            $table->boolean('is_offline_sync')->default(false)->after('verified_via_beacon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['beacon_uuid', 'beacon_major', 'beacon_minor']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('is_offline_sync');
        });
    }
};
