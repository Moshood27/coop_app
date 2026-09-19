<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fiscal_periods')) {
            Schema::create('fiscal_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // e.g., FY2026-P01
                $table->date('starts_on');
                $table->date('ends_on');
                $table->boolean('is_closed')->default(false);
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by')->nullable();
                $table->timestamps();

                $table->index(['starts_on', 'ends_on']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
    }
};
