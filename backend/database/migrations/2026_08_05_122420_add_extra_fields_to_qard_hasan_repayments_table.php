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
        Schema::table('qard_hasan_repayments', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('amount');
            $table->text('notes')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('qard_hasan_repayments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'notes']);
        });
    }
};
