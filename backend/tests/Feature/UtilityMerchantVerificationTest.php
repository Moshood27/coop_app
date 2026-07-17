<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\User;

class UtilityMerchantVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_merchant_fails_when_provider_returns_na_customer_name()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        // Mocking ClubKonnect/Nellobyte response
        Http::fake([
            '*' => Http::response([
                'customer_name' => 'N/A',
                'customer_address' => 'N/A',
                'status' => '100'
            ], 200)
        ]);

        $response = $this->postJson('/api/vtu/verify-merchant', [
            'serviceID' => 'eko-electric',
            'billersCode' => '10101010101',
            'type' => 'prepaid'
        ]);

        // It should NOT be successful
        $response->assertStatus(422);
        // Message might have sandbox note if enabled in test env
        $response->assertJson(fn (\Illuminate\Testing\Fluent\AssertableJson $json) =>
            $json->has('message')
                 ->where('message', fn ($m) => str_contains($m, 'Invalid Meter/Smartcard Number'))
                 ->etc()
        );
    }

    public function test_verify_merchant_fails_when_provider_returns_invalid_customer_name()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        Http::fake([
            '*' => Http::response([
                'customer_name' => 'INVALID METER NUMBER',
                'status' => '200'
            ], 200)
        ]);

        $response = $this->postJson('/api/vtu/verify-merchant', [
            'serviceID' => 'eko-electric',
            'billersCode' => '10101010101',
            'type' => 'prepaid'
        ]);

        $response->assertStatus(422);
        // Message might have sandbox note if enabled in test env
        $response->assertJson(fn (\Illuminate\Testing\Fluent\AssertableJson $json) =>
            $json->has('message')
                 ->where('message', fn ($m) => str_contains($m, 'Invalid Meter/Smartcard Number'))
                 ->etc()
        );
    }

    public function test_verify_merchant_succeeds_with_valid_name()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        Http::fake([
            '*' => Http::response([
                'customer_name' => 'JOHN DOE',
                'customer_address' => '123 Test St',
                'status' => '200'
            ], 200)
        ]);

        $response = $this->postJson('/api/vtu/verify-merchant', [
            'serviceID' => 'eko-electric',
            'billersCode' => '10101010101',
            'type' => 'prepaid'
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('customer_name', 'JOHN DOE');
    }

    public function test_verify_merchant_returns_auth_error_from_clubkonnect()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        Http::fake([
            '*' => Http::response([
                'status' => 'AUTHENTICATION_FAILED_1'
            ], 200)
        ]);

        $response = $this->postJson('/api/vtu/verify-merchant', [
            'serviceID' => '01',
            'billersCode' => '1234567890',
            'type' => 'prepaid'
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Provider authentication failed. Please update ClubKonnect/Nellobyte or VTpass credentials in the system configuration.']);
    }
}
