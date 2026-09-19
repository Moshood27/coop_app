<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ledger_accounts')) {
            return; // safety in case core ledger not yet migrated
        }

        $now = now();

        // Helper: insert if not exists by code
        $ensure = function (string $code, string $name, string $type) use ($now) {
            $exists = DB::table('ledger_accounts')->where('code', $code)->exists();
            if (!$exists) {
                DB::table('ledger_accounts')->insert([
                    'code' => $code,
                    'name' => $name,
                    'type' => $type, // asset/liability/equity/income/expense
                    'parent_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        };

        // Inventory (Asset)
        $ensure('1200', 'Inventory', 'asset');
        // VAT Output (Liability)
        $ensure('2410', 'VAT Output', 'liability');
        // VAT Input (Asset) - reserved for future input postings
        $ensure('1410', 'VAT Input', 'asset');
        // COGS (Expense)
        $ensure('5100', 'Cost of Goods Sold', 'expense');
    }

    public function down(): void
    {
        if (!Schema::hasTable('ledger_accounts')) return;
        DB::table('ledger_accounts')->whereIn('code', ['1200', '2410', '1410', '5100'])->delete();
    }
};
