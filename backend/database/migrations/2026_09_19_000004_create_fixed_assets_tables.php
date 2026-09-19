<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_categories')) {
            Schema::create('asset_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('method', ['straight_line', 'reducing_balance'])->default('straight_line');
                $table->unsignedInteger('useful_life_months')->default(36);
                $table->decimal('rate_percent', 5, 2)->default(0); // for reducing balance
                $table->foreignId('asset_account_id')->constrained('ledger_accounts');
                $table->foreignId('accum_dep_account_id')->constrained('ledger_accounts');
                $table->foreignId('dep_expense_account_id')->constrained('ledger_accounts');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_category_id')->constrained('asset_categories');
                $table->string('name');
                $table->string('code')->nullable()->unique();
                $table->date('acquisition_date');
                $table->decimal('acquisition_cost', 15, 2);
                $table->decimal('residual_value', 15, 2)->default(0);
                $table->foreignId('asset_account_id')->nullable()->constrained('ledger_accounts');
                $table->foreignId('accum_dep_account_id')->nullable()->constrained('ledger_accounts');
                $table->foreignId('dep_expense_account_id')->nullable()->constrained('ledger_accounts');
                $table->foreignId('branch_id')->nullable()->constrained('branches');
                $table->string('status')->default('active'); // active|disposed
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('asset_depreciations')) {
            Schema::create('asset_depreciations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('amount', 15, 2);
                $table->foreignId('ledger_journal_id')->nullable()->constrained('ledger_journals');
                $table->timestamp('posted_at')->nullable();
                $table->string('status')->default('scheduled'); // scheduled|posted|skipped
                $table->timestamps();
                $table->unique(['asset_id', 'period_start', 'period_end']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
