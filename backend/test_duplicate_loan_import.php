<?php

use App\Imports\LoansImport;
use App\Models\User;
use App\Models\QardHasan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Use SQLite for testing
config(['database.default' => 'sqlite']);
config(['database.connections.sqlite' => [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]]);

// Run migrations
Schema::create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('membership_number')->unique()->nullable();
    $table->string('password');
    $table->unsignedBigInteger('branch_id')->nullable();
    $table->string('approval_status')->default('pending');
    $table->timestamps();
});

Schema::create('qard_hasans', function ($table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('qard_id_string')->unique();
    $table->decimal('principal_amount', 15, 2);
    $table->decimal('paid_amount', 15, 2)->default(0);
    $table->integer('total_installments');
    $table->decimal('per_installment', 15, 2);
    $table->string('interval');
    $table->string('status');
    $table->dateTime('approved_at')->nullable();
    $table->dateTime('received_at')->nullable();
    $table->dateTime('defaulted_at')->nullable();
    $table->timestamps();
});

Schema::create('breezy_sessions', function ($table) {
    $table->id();
    $table->string('authenticatable_type');
    $table->unsignedBigInteger('authenticatable_id');
    $table->timestamps();
});

Schema::create('activity_log', function ($table) {
    $table->id();
    $table->string('log_name')->nullable();
    $table->text('description');
    $table->nullableMorphs('subject');
    $table->nullableMorphs('causer');
    $table->json('properties')->nullable();
    $table->uuid('batch_uuid')->nullable();
    $table->string('event')->nullable();
    $table->timestamps();
    $table->index('log_name');
});

Schema::create('settings', function ($table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->timestamps();
});

// Create a test user
$membershipNo = 'TEST_DUP_001';
$user = User::updateOrCreate(
    ['membership_number' => $membershipNo],
    [
        'name' => 'Test Duplicate User',
        'email' => 'test_dup@example.com',
        'password' => bcrypt('password'),
    ]
);

echo "User ID: " . $user->id . "\n";

// Clean up existing loans for this user to start fresh
QardHasan::where('user_id', $user->id)->delete();

$import = new LoansImport(now());

$row = [
    'membership_no' => $membershipNo,
    'original_loan_amount' => '100000',
    'remaining_principal' => '40000',
    'next_installment_amount' => '10000',
    'total_repaid_to_date' => '60000',
    'interval' => 'monthly',
    'total_installments' => '10',
    'received_at' => '2026-01-01',
    'defaulted_at' => '',
];

echo "First import...\n";
$model1 = $import->model($row);
if ($model1) {
    $model1->save();
    echo "Loan 1 saved. ID: " . $model1->id . "\n";
}

echo "Second import (same data)...\n";
$model2 = $import->model($row);
if ($model2) {
    $model2->save();
    echo "Loan 2 saved. ID: " . $model2->id . "\n";
} else {
    echo "Loan 2 was NOT created (expected behavior after fix).\n";
}

$count = QardHasan::where('user_id', $user->id)->count();
echo "Total loans for user: $count\n";

if ($count > 1) {
    echo "FAIL: Duplicate loans found!\n";
} else {
    echo "PASS: No duplicate loans.\n";
}
