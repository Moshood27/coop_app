<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add helpful indexes if tables exist
        if (Schema::hasTable('ledger_entries')) {
            Schema::table('ledger_entries', function (Blueprint $table) {
                $table->index(['ledger_account_id']);
                if (Schema::hasColumn('ledger_entries', 'branch_id')) {
                    $table->index(['branch_id']);
                }
            });
        }
        if (Schema::hasTable('ledger_journals')) {
            Schema::table('ledger_journals', function (Blueprint $table) {
                if (!Schema::hasColumn('ledger_journals', 'external_key')) return;
                $table->unique('external_key');
                $table->index(['date']);
                if (Schema::hasColumn('ledger_journals', 'number')) {
                    $table->unique('number');
                }
            });
        }

        // Add CHECK constraints where supported (best-effort; MySQL < 8 may ignore)
        try {
            if (Schema::hasTable('ledger_entries')) {
                DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT chk_debit_credit_nonnegative CHECK (debit >= 0 AND credit >= 0)");
                DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT chk_debit_xor_credit CHECK (NOT (debit > 0 AND credit > 0))");
            }
        } catch (\Throwable $e) {
            // Some engines ignore CHECK; safe to skip
        }
    }

    public function down(): void
    {
        try {
            if (Schema::hasTable('ledger_entries')) {
                DB::statement("ALTER TABLE ledger_entries DROP CONSTRAINT chk_debit_credit_nonnegative");
            }
        } catch (\Throwable $e) {}
        try {
            if (Schema::hasTable('ledger_entries')) {
                DB::statement("ALTER TABLE ledger_entries DROP CONSTRAINT chk_debit_xor_credit");
            }
        } catch (\Throwable $e) {}

        if (Schema::hasTable('ledger_entries')) {
            Schema::table('ledger_entries', function (Blueprint $table) {
                // dropping indexes by name may vary; ignore if fails
                try { $table->dropIndex(['ledger_account_id']); } catch (\Throwable $e) {}
                try { $table->dropIndex(['branch_id']); } catch (\Throwable $e) {}
            });
        }
        if (Schema::hasTable('ledger_journals')) {
            Schema::table('ledger_journals', function (Blueprint $table) {
                try { $table->dropUnique(['external_key']); } catch (\Throwable $e) {}
                try { $table->dropIndex(['date']); } catch (\Throwable $e) {}
                try { $table->dropUnique(['number']); } catch (\Throwable $e) {}
            });
        }
    }
};
