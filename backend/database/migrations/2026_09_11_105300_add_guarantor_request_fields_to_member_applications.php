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
        Schema::table('member_applications', function (Blueprint $blueprint) {
            $blueprint->foreignId('guarantor_id')->nullable()->after('guarantor_signature_path')->constrained('users')->nullOnDelete();
            $blueprint->string('guarantor_status')->default('pending')->after('guarantor_id'); // pending, accepted, declined
            $blueprint->timestamp('guarantor_responded_at')->nullable()->after('guarantor_status');
            $blueprint->timestamp('last_guarantor_reminder_sent_at')->nullable()->after('guarantor_responded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_applications', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['guarantor_id']);
            $blueprint->dropColumn([
                'guarantor_id',
                'guarantor_status',
                'guarantor_responded_at',
                'last_guarantor_reminder_sent_at'
            ]);
        });
    }
};
