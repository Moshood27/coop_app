<?php
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Jobs\AutoRecoverOverdueLoans;
use Illuminate\Support\Facades\Queue;

Queue::fake();
Setting::set('auto_overdue_recovery_enabled', true);
$user = User::factory()->create();
WalletTransaction::create(['user_id' => $user->id, 'type' => 'credit', 'amount' => 100, 'reference' => 'T1-' . uniqid(), 'source' => 'manual']);
try {
    Queue::assertPushed(AutoRecoverOverdueLoans::class);
    echo "ENABLED: PUSHED OK\n";
} catch (\Exception $e) {
    echo "ENABLED: NOT PUSHED FAIL\n";
}

Queue::fake();
Setting::set('auto_overdue_recovery_enabled', false);
WalletTransaction::create(['user_id' => $user->id, 'type' => 'credit', 'amount' => 100, 'reference' => 'T2-' . uniqid(), 'source' => 'manual']);
try {
    Queue::assertNotPushed(AutoRecoverOverdueLoans::class);
    echo "DISABLED: NOT PUSHED OK\n";
} catch (\Exception $e) {
    echo "DISABLED: PUSHED FAIL\n";
}
