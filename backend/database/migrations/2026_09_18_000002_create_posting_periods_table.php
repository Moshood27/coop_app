<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->string('name'); // e.g., 2026-01
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_open')->default(true);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->timestamps();
            $table->unique(['fiscal_year_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_periods');
    }
};
