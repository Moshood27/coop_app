<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Scheme;
use App\Services\GoldSilverPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardGoldPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_hit_gold_api()
    {
        // Setup required data
        Scheme::create(['name' => 'Savings']);
        Scheme::create(['name' => 'Shares']);
        Scheme::create(['name' => 'Ordinary Savings']);
        Scheme::create(['name' => 'Special Savings']);
        Scheme::create(['name' => 'Share Capital']);
        Scheme::create(['name' => 'Digital Gold']);

        $user = User::factory()->create();

        // Mock GoldAPI
        Http::fake([
            'https://www.goldapi.io/api/*' => Http::response(['price' => 100000], 200),
        ]);

        // Ensure cache is empty
        Cache::flush();

        // Call dashboard API
        $response = $this->actingAs($user, 'api')->getJson('/api/dashboard');

        $response->assertStatus(200);

        // GoldAPI should NOT have been called because Dashboard should use restricted (cached/mock) price
        Http::assertNothingSent();
    }

    public function test_zakat_estimate_page_hits_gold_api_when_not_from_dashboard()
    {
        // Setup required data
        Scheme::create(['name' => 'Savings']);
        Scheme::create(['name' => 'Shares']);
        Scheme::create(['name' => 'Ordinary Savings']);
        Scheme::create(['name' => 'Special Savings']);
        Scheme::create(['name' => 'Share Capital']);
        Scheme::create(['name' => 'Digital Gold']);

        $user = User::factory()->create();

        // Mock GoldAPI
        Http::fake([
            'https://www.goldapi.io/api/XAU/NGN' => Http::response(['price' => 100000], 200),
        ]);

        // Ensure cache is empty
        Cache::flush();

        // Call zakat estimate API directly (which should allow live by default)
        $response = $this->actingAs($user, 'api')->getJson('/api/zakat/estimate');

        $response->assertStatus(200);

        // GoldAPI SHOULD have been called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'goldapi.io/api/XAU/NGN');
        });
    }
}
