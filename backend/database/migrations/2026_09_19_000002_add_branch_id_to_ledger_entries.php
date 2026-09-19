<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ledger_entries', 'branch_id')) {
            Schema::table('ledger_entries', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('ledger_account_id')->constrained('branches');
                $table->index(['branch_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ledger_entries', 'branch_id')) {
            Schema::table('ledger_entries', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
                $table->dropIndex(['branch_id']);
            });
        }
    }
};
