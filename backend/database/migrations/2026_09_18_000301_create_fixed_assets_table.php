<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->date('acquisition_date');
            $table->decimal('cost', 18, 2);
            $table->unsignedInteger('useful_life_months');
            $table->decimal('residual_value', 18, 2)->default(0);
            $table->string('depreciation_method')->default('straight_line');
            $table->boolean('is_disposed')->default(false);
            $table->date('disposed_at')->nullable();
            $table->decimal('disposal_amount', 18, 2)->nullable();
            $table->foreignId('ledger_journal_id')->nullable()->constrained('ledger_journals')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
