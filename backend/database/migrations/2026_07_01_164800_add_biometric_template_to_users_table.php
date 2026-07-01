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
            $table->longText('biometric_template')->nullable();
        });

        Schema::table('member_applications', function (Blueprint $table) {
            $table->longText('biometric_template')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('biometric_template');
        });

        Schema::table('member_applications', function (Blueprint $table) {
            $table->dropColumn('biometric_template');
        });
    }
};
