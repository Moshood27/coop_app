<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('account_number')->nullable();
                $table->foreignId('ledger_account_id')->constrained('ledger_accounts');
                $table->foreignId('branch_id')->nullable()->constrained('branches');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bank_statements')) {
            Schema::create('bank_statements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_account_id')->constrained('bank_accounts')->onDelete('cascade');
                $table->date('period_from');
                $table->date('period_to');
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->decimal('closing_balance', 15, 2)->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users');
                $table->string('status')->default('draft'); // draft|imported|reconciled
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bank_statement_lines')) {
            Schema::create('bank_statement_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_statement_id')->constrained('bank_statements')->onDelete('cascade');
                $table->date('txn_date');
                $table->decimal('amount', 15, 2);
                $table->string('description')->nullable();
                $table->string('reference')->nullable();
                $table->string('hash')->nullable()->index();
                $table->string('status')->default('unmatched'); // unmatched|matched|excluded
                $table->foreignId('matched_journal_id')->nullable()->constrained('ledger_journals');
                $table->foreignId('matched_entry_id')->nullable()->constrained('ledger_entries');
                $table->timestamps();
                $table->index(['txn_date', 'amount']);
            });
        }

        if (!Schema::hasTable('bank_reconciliations')) {
            Schema::create('bank_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_account_id')->constrained('bank_accounts')->onDelete('cascade');
                $table->date('period_from');
                $table->date('period_to');
                $table->decimal('ending_balance', 15, 2)->default(0);
                $table->string('status')->default('in_progress'); // in_progress|finalized
                $table->foreignId('reconciled_by')->nullable()->constrained('users');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('bank_matches')) {
            Schema::create('bank_matches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reconciliation_id')->constrained('bank_reconciliations')->onDelete('cascade');
                $table->foreignId('bank_statement_line_id')->constrained('bank_statement_lines')->onDelete('cascade');
                $table->foreignId('ledger_journal_id')->nullable()->constrained('ledger_journals');
                $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries');
                $table->decimal('match_amount', 15, 2);
                $table->string('method')->default('manual'); // auto|manual
                $table->timestamps();
                $table->index(['bank_statement_line_id', 'ledger_entry_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_matches');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('bank_accounts');
    }
};
