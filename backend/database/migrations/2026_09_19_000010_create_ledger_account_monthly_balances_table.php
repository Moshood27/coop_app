<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ledger_account_monthly_balances')) {
            Schema::create('ledger_account_monthly_balances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ledger_account_id');
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month'); // 1..12
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->decimal('opening_balance', 18, 2)->default(0);
                $table->decimal('debits', 18, 2)->default(0);
                $table->decimal('credits', 18, 2)->default(0);
                $table->decimal('closing_balance', 18, 2)->default(0);
                $table->timestamps();

                $table->unique(['ledger_account_id', 'year', 'month', 'branch_id'], 'uniq_acc_year_month_branch');
                $table->index(['year', 'month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_account_monthly_balances');
    }
};
