<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ledger_attachments')) {
            Schema::create('ledger_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ledger_journal_id');
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime_type', 128)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index('ledger_journal_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_attachments');
    }
};
