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
            if (!Schema::hasColumn('users', 'dawah_fund_balance')) {
                $table->decimal('dawah_fund_balance', 15, 2)->default(0)->after('group_savings_balance');
            }
            if (!Schema::hasColumn('users', 'sitting_balance')) {
                $table->decimal('sitting_balance', 15, 2)->default(0)->after('dawah_fund_balance');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dawah_fund_balance', 'sitting_balance']);
        });
    }
};
