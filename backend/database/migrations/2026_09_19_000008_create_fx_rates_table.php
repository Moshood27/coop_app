<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fx_rates')) {
            Schema::create('fx_rates', function (Blueprint $table) {
                $table->id();
                $table->char('currency_code', 3);
                $table->date('rate_date');
                $table->decimal('rate_to_base', 18, 8); // 1 unit of currency to base (e.g., NGN)
                $table->timestamps();
                $table->unique(['currency_code', 'rate_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
