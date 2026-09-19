<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('journal_sequences')) {
            Schema::create('journal_sequences', function (Blueprint $table) {
                $table->id();
                $table->integer('year')->index();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
                $table->unique(['year']);
            });
        }

        if (!Schema::hasColumn('ledger_journals', 'number')) {
            Schema::table('ledger_journals', function (Blueprint $table) {
                $table->string('number')->nullable()->unique()->after('date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ledger_journals', 'number')) {
            Schema::table('ledger_journals', function (Blueprint $table) {
                $table->dropUnique(['number']);
                $table->dropColumn('number');
            });
        }
        Schema::dropIfExists('journal_sequences');
    }
};
