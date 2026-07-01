<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->foreignId('qard_hasan_id')->nullable()->after('scheme_id')->constrained('qard_hasans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qard_hasan_id');
        });
    }
};
