<?php

namespace Tests\Feature;

use App\Console\Commands\BackfillUserSchemeBalances;
use App\Models\Contribution;
use App\Models\Scheme;
use App\Models\User;
use App\Models\UserSchemeBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class UserSchemeBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_scheme_balance_upserts_normalized_rows_and_legacy_columns()
    {
        $user = User::factory()->create();
        $savings = Scheme::create(['name' => 'Savings', 'active' => true]);
        $ordinary = Scheme::create(['name' => 'Ordinary Savings', 'active' => true]);

        Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $savings->id,
            'amount' => 1000,
            'status' => 'success',
        ]);
        Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $ordinary->id,
            'amount' => 500,
            'status' => 'success',
        ]);

        // Act
        $user->syncSchemeBalance('Savings');

        // Assert per-scheme rows
        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $savings->id,
            'balance' => 1000.00,
        ]);
        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $ordinary->id,
            'balance' => 500.00,
        ]);

        // Legacy column ordinary_savings should hold the total of mapped schemes
        $this->assertEquals(1500.00, (float) $user->fresh()->ordinary_savings);
    }

    public function test_backfill_command_populates_balances_from_contributions_and_legacy()
    {
        $user = User::factory()->create([
            'fine_balance' => 200.00, // legacy fallback for Fine if no contributions
        ]);
        $shares = Scheme::create(['name' => 'Shares', 'active' => true]);
        $fine = Scheme::create(['name' => 'Fine', 'active' => true]);

        Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $shares->id,
            'amount' => 750.50,
            'status' => 'success',
        ]);

        // Run
        Artisan::call('balances:backfill', ['--chunk' => 50]);

        // From contributions
        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $shares->id,
            'balance' => 750.50,
        ]);

        // From legacy column (Fine) since there is no contribution for Fine
        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $fine->id,
            'balance' => 200.00,
        ]);
    }

    public function test_backfill_skips_contributions_with_invalid_scheme_id()
    {
        $user = User::factory()->create();
        $shares = Scheme::create(['name' => 'Shares', 'active' => true]);

        // Orphan contribution with invalid scheme_id = 0
        Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => 0,
            'amount' => 100,
            'status' => 'success',
        ]);

        // Valid contribution
        Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $shares->id,
            'amount' => 50,
            'status' => 'success',
        ]);

        // Run backfill; should not throw FK error and should ignore scheme_id 0
        Artisan::call('balances:backfill', ['--chunk' => 50]);

        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $shares->id,
            'balance' => 50.00,
        ]);

        $this->assertDatabaseMissing('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => 0,
        ]);
    }
}
