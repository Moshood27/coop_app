<?php

namespace Tests\Feature;

use App\Console\Commands\BackfillUserSchemeBalances;
use App\Models\Contribution;
use App\Models\Scheme;
use App\Models\User;
use App\Models\UserSchemeBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
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

    public function test_contribution_update_resyncs_balances_and_removes_old_scheme()
    {
        $user = User::factory()->create();
        $s1 = Scheme::create(['name' => 'Savings', 'active' => true]);
        $s2 = Scheme::create(['name' => 'Shares', 'active' => true]);

        $c = Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
            'amount' => 100,
            'status' => 'success',
        ]);

        // After creation, balance exists for s1
        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
            'balance' => 100.00,
        ]);

        // Update amount
        $c->update(['amount' => 150]);
        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
            'balance' => 150.00,
        ]);

        // Move to another scheme; old scheme row should be removed by sync
        $c->update(['scheme_id' => $s2->id]);
        $this->assertDatabaseMissing('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
        ]);
        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $s2->id,
            'balance' => 150.00,
        ]);
    }

    public function test_contribution_delete_resyncs_balances()
    {
        $user = User::factory()->create();
        $s1 = Scheme::create(['name' => 'Savings', 'active' => true]);

        $c1 = Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
            'amount' => 100,
            'status' => 'success',
        ]);
        $c2 = Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
            'amount' => 50,
            'status' => 'success',
        ]);

        // Total 150
        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
            'balance' => 150.00,
        ]);

        // Delete one contribution -> balance should reduce to 50
        $c1->delete();
        $this->assertDatabaseHas('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
            'balance' => 50.00,
        ]);

        // Delete the last one -> row should be removed
        $c2->delete();
        $this->assertDatabaseMissing('user_scheme_balances', [
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
        ]);
    }

    public function test_scheme_balances_endpoint_returns_payload()
    {
        $user = User::factory()->create();
        $s1 = Scheme::create(['name' => 'Savings', 'active' => true]);
        $s2 = Scheme::create(['name' => 'Shares', 'active' => true]);

        Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $s1->id,
            'amount' => 200,
            'status' => 'success',
        ]);
        Contribution::create([
            'user_id' => $user->id,
            'scheme_id' => $s2->id,
            'amount' => 300,
            'status' => 'success',
        ]);

        // Authenticate via Sanctum
        Sanctum::actingAs($user);

        $resp = $this->getJson('/api/scheme-balances');
        $resp->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['scheme_id', 'scheme_name', 'active', 'balance', 'updated_at']
                ],
                'total'
            ]);

        $data = $resp->json('data');
        $schemes = collect($data)->keyBy('scheme_id');
        $this->assertEquals(200.0, (float) $schemes[$s1->id]['balance']);
        $this->assertEquals(300.0, (float) $schemes[$s2->id]['balance']);
        $this->assertEquals(500.0, (float) $resp->json('total'));
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
