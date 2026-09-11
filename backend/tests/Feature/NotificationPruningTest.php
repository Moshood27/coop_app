<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class NotificationPruningTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_read_notifications_older_than_30_days()
    {
        $user = User::factory()->create();

        // Old read notification (31 days ago) - should be deleted
        DB::table('notifications')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'Old read']),
            'read_at' => Carbon::now()->subDays(31),
            'created_at' => Carbon::now()->subDays(31),
            'updated_at' => Carbon::now()->subDays(31),
        ]);

        // Recent read notification (10 days ago) - should be kept
        DB::table('notifications')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'Recent read']),
            'read_at' => Carbon::now()->subDays(10),
            'created_at' => Carbon::now()->subDays(10),
            'updated_at' => Carbon::now()->subDays(10),
        ]);

        // Old unread notification (31 days ago) - should be kept
        DB::table('notifications')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'Old unread']),
            'read_at' => null,
            'created_at' => Carbon::now()->subDays(31),
            'updated_at' => Carbon::now()->subDays(31),
        ]);

        $this->assertEquals(3, DB::table('notifications')->count());

        Artisan::call('notifications:prune');

        $this->assertEquals(2, DB::table('notifications')->count());
        $this->assertDatabaseMissing('notifications', ['data' => json_encode(['message' => 'Old read'])]);
        $this->assertDatabaseHas('notifications', ['data' => json_encode(['message' => 'Recent read'])]);
        $this->assertDatabaseHas('notifications', ['data' => json_encode(['message' => 'Old unread'])]);
    }

    public function test_limits_notifications_per_user_to_50()
    {
        $user = User::factory()->create();

        // Create 60 notifications for this user
        for ($i = 1; $i <= 60; $i++) {
            DB::table('notifications')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\TestNotification',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $user->id,
                'data' => json_encode(['index' => $i]),
                'read_at' => null,
                'created_at' => Carbon::now()->subMinutes(60 - $i),
                'updated_at' => Carbon::now()->subMinutes(60 - $i),
            ]);
        }

        $this->assertEquals(60, DB::table('notifications')->where('notifiable_id', $user->id)->count());

        Artisan::call('notifications:prune');

        // Should only keep the last 50
        $this->assertEquals(50, DB::table('notifications')->where('notifiable_id', $user->id)->count());

        // The first 10 should be gone (index 1 to 10)
        $this->assertDatabaseMissing('notifications', ['data' => json_encode(['index' => 1])]);
        $this->assertDatabaseMissing('notifications', ['data' => json_encode(['index' => 10])]);
        $this->assertDatabaseHas('notifications', ['data' => json_encode(['index' => 11])]);
        $this->assertDatabaseHas('notifications', ['data' => json_encode(['index' => 60])]);
    }
}
