<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->decimal('percent', 5, 2);
                $table->string('country')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_tax_rates')) {
            Schema::create('product_tax_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('tax_rate_id');
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->cascadeOnDelete();
                $table->unique(['product_id', 'tax_rate_id', 'effective_from'], 'ptr_unique');
            });
        }

        if (!Schema::hasTable('tax_transactions')) {
            Schema::create('tax_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_order_id')->nullable();
                $table->unsignedBigInteger('store_order_item_id')->nullable();
                $table->enum('direction', ['input', 'output'])->default('output');
                $table->decimal('rate_percent', 5, 2)->default(0);
                $table->decimal('base_amount', 16, 2)->default(0);
                $table->decimal('tax_amount', 16, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('store_order_id')->references('id')->on('store_orders')->nullOnDelete();
                $table->foreign('store_order_item_id')->references('id')->on('store_order_items')->nullOnDelete();
                $table->index(['direction']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_transactions');
        Schema::dropIfExists('product_tax_rates');
        Schema::dropIfExists('tax_rates');
    }
};
