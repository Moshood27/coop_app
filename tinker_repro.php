DB::beginTransaction();
try {
    echo "Creating user...\n";
    $user = App\Models\User::create([
        'name' => 'Test User',
        'email' => 'test_delete_' . time() . '@example.com',
        'password' => bcrypt('password'),
        'phone' => '1234567890',
    ]);
    echo "Creating ledger journal...\n";
    App\Models\LedgerJournal::create([
        'date' => now(),
        'reference' => 'TEST-001',
        'description' => 'Test Journal',
        'created_by' => $user->id,
    ]);
    echo "Attempting to delete user...\n";
    $user->delete();
    echo "User deleted successfully!\n";
} catch (\Exception $e) {
    echo "Caught exception: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
}
