<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the new table
        Schema::create('user_virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('paystack_customer_code')->nullable()->unique()->index();
            $table->string('paystack_authorization_code')->nullable();
            $table->string('dva_account_number')->nullable()->index();
            $table->string('dva_bank_name')->nullable();
            $table->string('dva_account_name')->nullable();
            $table->json('dva_verification_meta')->nullable();
            $table->json('flw_dva_data')->nullable();
            $table->string('monnify_customer_reference')->nullable()->index();
            $table->json('monnify_dva_data')->nullable();
            $table->timestamps();
        });

        // 2. Migrate existing data
        $columnsToMigrate = [
            'paystack_customer_code',
            'paystack_authorization_code',
            'dva_account_number',
            'dva_bank_name',
            'dva_account_name',
            'dva_verification_meta',
            'flw_dva_data',
            'monnify_customer_reference',
            'monnify_dva_data',
        ];

        $existingColumns = [];
        foreach ($columnsToMigrate as $column) {
            if (Schema::hasColumn('users', $column)) {
                $existingColumns[] = $column;
            }
        }

        if (!empty($existingColumns)) {
            $users = DB::table('users')->where(function ($query) use ($existingColumns) {
                foreach ($existingColumns as $column) {
                    $query->orWhereNotNull($column);
                }
            })->get();

            foreach ($users as $user) {
                $insertData = [
                    'user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                foreach ($columnsToMigrate as $column) {
                    if (property_exists($user, $column)) {
                        $insertData[$column] = $user->$column;
                    }
                }

                DB::table('user_virtual_accounts')->insert($insertData);
            }
        }

        // 3. Keep columns in users table for backward compatibility during transition
        // We will not drop them in this migration to ensure zero risk to production data.
        // A future migration can drop them once the new implementation is verified.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_virtual_accounts');
    }
};
