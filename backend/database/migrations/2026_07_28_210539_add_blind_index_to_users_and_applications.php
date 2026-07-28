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
            $table->string('phone_bindex')->nullable()->index()->after('phone');
            $table->string('bvn_bindex')->nullable()->index()->after('bvn');
        });

        Schema::table('member_applications', function (Blueprint $table) {
            $table->string('phone_bindex')->nullable()->index()->after('phone');
            $table->string('bvn_bindex')->nullable()->index()->after('bvn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_bindex', 'bvn_bindex']);
        });

        Schema::table('member_applications', function (Blueprint $table) {
            $table->dropColumn(['phone_bindex', 'bvn_bindex']);
        });
    }
};
