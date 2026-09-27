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
        Schema::create('user_scheme_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained()->restrictOnDelete();
            $table->decimal('balance', 16, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'scheme_id']);
            $table->index('scheme_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_scheme_balances');
    }
};
