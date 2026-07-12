<?php

use App\Models\User;
use App\Models\LedgerJournal;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

DB::beginTransaction();

try {
    echo "Creating user...\n";
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test_delete_' . time() . '@example.com',
        'password' => bcrypt('password'),
        'phone' => '1234567890',
    ]);

    echo "Creating ledger journal...\n";
    LedgerJournal::create([
        'date' => now(),
        'reference' => 'TEST-001',
        'description' => 'Test Journal',
        'created_by' => $user->id,
    ]);

    echo "Attempting to delete user...\n";
    $user->delete();
    echo "User deleted successfully (This should not happen if the issue exists)!\n";
} catch (\PDOException $e) {
    echo "Caught expected PDOException: " . $e->getMessage() . "\n";
    if ($e->getCode() == '23000') {
        echo "Successfully reproduced the integrity constraint violation!\n";
    } else {
        echo "Caught unexpected PDOException code: " . $e->getCode() . "\n";
    }
} catch (\Exception $e) {
    echo "Caught unexpected exception: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
}
