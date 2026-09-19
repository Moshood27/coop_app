<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('recurring_journals')) {
            Schema::create('recurring_journals', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('schedule')->nullable(); // cron or frequency string (e.g., monthly)
                $table->timestamp('next_run_at')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('template'); // [{ledger_account_id, debit, credit, description}]
                $table->text('description')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['is_active', 'next_run_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_journals');
    }
};
