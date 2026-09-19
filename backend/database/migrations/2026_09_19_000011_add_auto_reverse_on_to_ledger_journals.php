<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ledger_journals') && !Schema::hasColumn('ledger_journals', 'auto_reverse_on')) {
            Schema::table('ledger_journals', function (Blueprint $table) {
                $table->date('auto_reverse_on')->nullable()->after('posted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ledger_journals') && Schema::hasColumn('ledger_journals', 'auto_reverse_on')) {
            Schema::table('ledger_journals', function (Blueprint $table) {
                $table->dropColumn('auto_reverse_on');
            });
        }
    }
};
