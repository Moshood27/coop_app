<?php

namespace Tests\Feature;

use App\Services\Kyc\Providers\PaystackProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackKycProviderTest extends TestCase
{
    public function test_verify_bvn_success()
    {
        $bvn = '12345678901';

        Http::fake([
            "https://api.paystack.co/bank/resolve_bvn/{$bvn}" => Http::response([
                'status' => true,
                'message' => 'BVN resolved',
                'data' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                ]
            ], 200)
        ]);

        $provider = new PaystackProvider();
        $result = $provider->verifyBvnWithFace($bvn, 'path/to/selfie.jpg');

        $this->assertTrue($result['success']);
        $this->assertEquals('verified', $result['status']);
        $this->assertEquals('paystack', $result['provider']);
        $this->assertEquals('John', $result['meta']['paystack_data']['first_name']);
    }

    public function test_verify_bvn_invalid_length()
    {
        $provider = new PaystackProvider();
        $result = $provider->verifyBvnWithFace('123', 'path/to/selfie.jpg');

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_bvn', $result['status']);
    }

    public function test_verify_bvn_provider_error()
    {
        $bvn = '12345678901';

        Http::fake([
            "https://api.paystack.co/bank/resolve_bvn/{$bvn}" => Http::response([
                'status' => false,
                'message' => 'Invalid BVN'
            ], 400)
        ]);

        $provider = new PaystackProvider();
        $result = $provider->verifyBvnWithFace($bvn, 'path/to/selfie.jpg');

        $this->assertFalse($result['success']);
        $this->assertEquals('provider_error', $result['status']);
    }
}
