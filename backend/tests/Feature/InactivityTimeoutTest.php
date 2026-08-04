<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Laravel\Sanctum\PersonalAccessToken;

class InactivityTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Set a short timeout for testing
        config(['inactivity.timeout' => 2]);
        putenv('INACTIVITY_TIMEOUT_SECONDS=2');
    }

    public function test_it_allows_request_when_using_session_auth_transient_token()
    {
        $user = User::factory()->create();

        // Sanctum::actingAs with no second argument uses session-based auth (TransientToken)
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/dashboard');

        // It should NOT throw 500 Undefined property: Laravel\Sanctum\TransientToken::$created_at
        $response->assertStatus(200);
    }

    public function test_it_enforces_timeout_for_personal_access_tokens()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // First request to set last_used_at
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard')
            ->assertStatus(200);

        // Simulate inactivity by manually setting last_used_at in the past
        $accessToken = PersonalAccessToken::findToken($token);
        $accessToken->forceFill(['last_used_at' => now()->subSeconds(130)])->save();

        // Next request should be rejected (default timeout is 120s in the middleware if env is not set correctly in test)
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dashboard')
            ->assertStatus(401)
            ->assertJson(['message' => 'Session expired due to inactivity.']);
    }
}
