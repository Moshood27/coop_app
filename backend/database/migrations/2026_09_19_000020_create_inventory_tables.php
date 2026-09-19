<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Inventory transactions (receipts/issues/adjustments)
        if (!Schema::hasTable('inventory_transactions')) {
            Schema::create('inventory_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->enum('type', ['receipt', 'issue', 'adjustment']);
                $table->decimal('qty', 18, 6);
                $table->decimal('unit_cost', 18, 6)->nullable();
                $table->decimal('total_cost', 24, 6)->nullable();
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedBigInteger('ledger_journal_id')->nullable();
                $table->timestamp('performed_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'branch_id']);
                $table->index(['performed_at']);
                $table->index(['type']);
            });
        }

        // Optional: flag on products to indicate stock tracking (do not use at runtime until migrated)
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'track_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('track_stock')->default(false)->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_transactions')) {
            Schema::dropIfExists('inventory_transactions');
        }
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'track_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('track_stock');
            });
        }
    }
};
