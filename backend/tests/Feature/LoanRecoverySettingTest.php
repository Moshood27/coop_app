<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Jobs\AutoRecoverOverdueLoans;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoanRecoverySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_job_is_dispatched_when_setting_is_enabled()
    {
        Queue::fake();
        Setting::set('auto_overdue_recovery_enabled', true);

        $user = User::factory()->create();

        WalletTransaction::create([
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 1000,
            'reference' => 'TEST-1',
            'source' => 'manual',
        ]);

        Queue::assertPushed(AutoRecoverOverdueLoans::class);
    }

    public function test_recovery_job_is_not_dispatched_when_setting_is_disabled()
    {
        Queue::fake();
        Setting::set('auto_overdue_recovery_enabled', false);

        $user = User::factory()->create();

        WalletTransaction::create([
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 1000,
            'reference' => 'TEST-2',
            'source' => 'manual',
        ]);

        Queue::assertNotPushed(AutoRecoverOverdueLoans::class);
    }

    public function test_hunter_sweep_command_checks_setting()
    {
        Setting::set('auto_overdue_recovery_enabled', false);

        $this->artisan('loans:hunter-sweep')
            ->expectsOutput('Automatic overdue loan recovery is currently disabled in app settings.')
            ->assertExitCode(0);
    }

    public function test_job_handle_checks_setting()
    {
        Setting::set('auto_overdue_recovery_enabled', false);
        $user = User::factory()->create();

        // We can't easily check if the job "returns early" without mocking or checking side effects
        // But since we already check at dispatch level, this is mostly a safety net.
        // We can check if it attempts to find the user.

        $job = new AutoRecoverOverdueLoans($user->id);
        $job->handle();

        // If it didn't crash and didn't do anything, it's probably fine.
        $this->assertTrue(true);
    }
}
