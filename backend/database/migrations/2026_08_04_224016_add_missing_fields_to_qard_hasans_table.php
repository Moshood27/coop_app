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
        Schema::table('qard_hasans', function (Blueprint $table) {
            $table->text('description')->nullable()->after('qard_id_string');
            $table->timestamp('disbursed_at')->nullable()->after('received_at');
            $table->timestamp('repayment_start_date')->nullable()->after('disbursed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qard_hasans', function (Blueprint $table) {
            $table->dropColumn(['description', 'disbursed_at', 'repayment_start_date']);
        });
    }
};
