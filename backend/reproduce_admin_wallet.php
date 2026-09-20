<?php

use App\Models\User;
use App\Models\Scheme;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\AdminMemberController;
use Illuminate\Http\Request;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Request::capture());

// 1. Setup Admin and Member
DB::transaction(function() {
    $admin = User::firstOrCreate(
        ['email' => 'admin_test@example.com'],
        [
            'name' => 'Admin',
            'surname' => 'Test',
            'password' => bcrypt('password'),
            'is_admin' => true,
            'balance' => 10000
        ]
    );
    $admin->is_admin = true;
    $admin->balance = 10000;
    $admin->save();

    $member = User::firstOrCreate(
        ['email' => 'member_test@example.com'],
        [
            'name' => 'Member',
            'surname' => 'Test',
            'password' => bcrypt('password'),
            'balance' => 0
        ]
    );
    $member->balance = 0;
    $member->save();

    $scheme = Scheme::firstOrCreate(['name' => 'Ordinary Savings']);

    echo "Initial State:\n";
    echo "Admin Balance: {$admin->balance}\n";
    echo "Member Balance: {$member->balance}\n";
    echo "Member Ordinary Savings: {$member->ordinary_savings}\n\n";

    // 2. Simulate Admin Allocation
    $controller = new AdminMemberController();
    $request = Request::create('/api/admin/members/' . $member->id . '/allocate-from-admin', 'POST', [
        'allocations' => [
            ['scheme_id' => $scheme->id, 'amount' => 5000]
        ],
        'notes' => 'Test allocation'
    ]);
    $request->setUserResolver(fn() => $admin);

    echo "Running Admin Allocation of 5000...\n";
    $response = $controller->allocateFromAdminWallet($request, $member);

    $admin->refresh();
    $member->refresh();

    echo "Final State:\n";
    echo "Admin Balance: {$admin->balance}\n";
    echo "Member Balance: {$member->balance}\n";
    echo "Member Ordinary Savings: {$member->ordinary_savings}\n";

    $adminTx = WalletTransaction::where('user_id', $admin->id)->latest()->first();
    $memberTx = WalletTransaction::where('user_id', $member->id)->latest()->first();

    echo "\nTransactions:\n";
    echo "Admin Tx: {$adminTx->type}, Amount: {$adminTx->amount}, Source: {$adminTx->source}\n";
    echo "Member Tx: {$memberTx->type}, Amount: {$memberTx->amount}, Source: {$memberTx->source}\n";

    if ($admin->balance == 5000 && $member->ordinary_savings == 5000) {
        echo "\nSUCCESS: Admin allocation worked correctly.\n";
    } else {
        echo "\nFAILURE: Balances don't match expected values.\n";
    }
});
