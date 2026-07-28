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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone_bindex')) {
                $table->string('phone_bindex')->nullable()->index()->after('phone');
            }
            if (!Schema::hasColumn('users', 'bvn_bindex')) {
                $table->string('bvn_bindex')->nullable()->index()->after('bvn');
            }
        });

        Schema::table('member_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('member_applications', 'phone_bindex')) {
                $table->string('phone_bindex')->nullable()->index()->after('phone');
            }
            // Only add bvn_bindex if bvn column exists
            if (Schema::hasColumn('member_applications', 'bvn') && !Schema::hasColumn('member_applications', 'bvn_bindex')) {
                $table->string('bvn_bindex')->nullable()->index()->after('bvn');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone_bindex')) {
                $table->dropColumn('phone_bindex');
            }
            if (Schema::hasColumn('users', 'bvn_bindex')) {
                $table->dropColumn('bvn_bindex');
            }
        });

        Schema::table('member_applications', function (Blueprint $table) {
            if (Schema::hasColumn('member_applications', 'phone_bindex')) {
                $table->dropColumn('phone_bindex');
            }
            if (Schema::hasColumn('member_applications', 'bvn_bindex')) {
                $table->dropColumn('bvn_bindex');
            }
        });
    }
};
