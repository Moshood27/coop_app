<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ledger_journals')) {
            Schema::table('ledger_journals', function (Blueprint $table) {
                if (!Schema::hasColumn('ledger_journals', 'period_id')) {
                    $table->unsignedBigInteger('period_id')->nullable()->after('date');
                }
                if (!Schema::hasColumn('ledger_journals', 'status')) {
                    $table->string('status', 32)->nullable()->after('description'); // draft, pending_approval, approved, posted, rejected
                }
                if (!Schema::hasColumn('ledger_journals', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('created_by');
                }
                if (!Schema::hasColumn('ledger_journals', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
                if (!Schema::hasColumn('ledger_journals', 'posted_at')) {
                    $table->timestamp('posted_at')->nullable()->after('approved_at');
                }
                if (!Schema::hasColumn('ledger_journals', 'reversing_journal_id')) {
                    $table->unsignedBigInteger('reversing_journal_id')->nullable()->after('posted_at');
                }
                if (!Schema::hasColumn('ledger_journals', 'external_key')) {
                    $table->string('external_key', 191)->nullable()->after('reference');
                }
                if (!Schema::hasColumn('ledger_journals', 'currency_code')) {
                    $table->char('currency_code', 3)->nullable()->after('number');
                }
                if (!Schema::hasColumn('ledger_journals', 'fx_rate')) {
                    $table->decimal('fx_rate', 18, 8)->nullable()->after('currency_code');
                }

                $table->index('period_id');
                $table->index('status');
                $table->index('approved_by');
                $table->index('reversing_journal_id');
                $table->unique('external_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ledger_journals')) {
            Schema::table('ledger_journals', function (Blueprint $table) {
                foreach ([
                    'period_id', 'status', 'approved_by', 'approved_at', 'posted_at',
                    'reversing_journal_id', 'external_key', 'currency_code', 'fx_rate'
                ] as $col) {
                    if (Schema::hasColumn('ledger_journals', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
