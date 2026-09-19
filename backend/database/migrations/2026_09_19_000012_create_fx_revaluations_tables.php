<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fx_revaluations')) {
            Schema::create('fx_revaluations', function (Blueprint $table) {
                $table->id();
                $table->date('valuation_date');
                $table->string('base_currency', 3)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('journal_id')->nullable(); // batch journal for totals if used
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('fx_revaluation_lines')) {
            Schema::create('fx_revaluation_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fx_revaluation_id');
                $table->unsignedBigInteger('ledger_account_id');
                $table->string('currency_code', 3);
                $table->decimal('balance_foreign', 24, 8)->default(0);
                $table->decimal('rate_old', 16, 8)->default(1);
                $table->decimal('rate_new', 16, 8)->default(1);
                $table->decimal('adjustment_local', 18, 2)->default(0);
                $table->unsignedBigInteger('journal_id')->nullable();
                $table->timestamps();
                $table->index(['fx_revaluation_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_revaluation_lines');
        Schema::dropIfExists('fx_revaluations');
    }
};
