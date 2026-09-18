<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->decimal('amount', 18, 2);
            $table->string('status')->default('draft');
            $table->foreignId('ledger_journal_id')->nullable()->constrained('ledger_journals')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoices');
    }
};
