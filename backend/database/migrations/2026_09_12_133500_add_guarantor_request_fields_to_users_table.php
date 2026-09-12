<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('users', 'guarantor_id')) {
                $blueprint->foreignId('guarantor_id')->nullable()->after('guarantor_signature_path')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'guarantor_status')) {
                $blueprint->string('guarantor_status')->default('none')->after('guarantor_id'); // none, pending, accepted, declined
            }
            if (!Schema::hasColumn('users', 'guarantor_responded_at')) {
                $blueprint->timestamp('guarantor_responded_at')->nullable()->after('guarantor_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['guarantor_id']);
            $blueprint->dropColumn([
                'guarantor_id',
                'guarantor_status',
                'guarantor_responded_at'
            ]);
        });
    }
};
