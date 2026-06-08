<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AttendanceQrTest extends TestCase
{
    use RefreshDatabase;

    protected AttendanceService $attendanceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attendanceService = app(AttendanceService::class);
    }

    public function test_can_generate_attendance_qr_payload()
    {
        $branch = Branch::factory()->create();
        $meeting = Meeting::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'ongoing',
        ]);

        $payload = $this->attendanceService->getAttendanceQrPayload($meeting);

        $this->assertStringStartsWith('attaqwa:attendance?', $payload);
        $this->assertStringContainsString('meeting_id=' . $meeting->id, $payload);
        $this->assertStringContainsString('token=', $payload);

        $cacheKey = "meeting_{$meeting->id}_qr_token";
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_qr_token_refreshes_on_scan()
    {
        $branch = Branch::factory()->create();
        $meeting = Meeting::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'ongoing',
            'venue_lat' => 10.0,
            'venue_lng' => 10.0,
        ]);
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $payload = $this->attendanceService->getAttendanceQrPayload($meeting);
        preg_match('/token=([^&]+)/', $payload, $matches);
        $token = $matches[1];

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/mark-attendance", [
                'qr_token' => $token,
                'lat' => 10.0,
                'lng' => 10.0,
                'device_uuid' => 'test-device-1',
            ])
            ->assertStatus(200);

        // Token should be different now
        $newPayload = $this->attendanceService->getAttendanceQrPayload($meeting);
        $this->assertNotEquals($payload, $newPayload);
    }

    public function test_invalid_qr_token_fails()
    {
        $branch = Branch::factory()->create();
        $meeting = Meeting::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'ongoing',
            'venue_lat' => 10.0,
            'venue_lng' => 10.0,
        ]);
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/mark-attendance", [
                'qr_token' => 'invalid-token',
                'lat' => 10.0,
                'lng' => 10.0,
                'device_uuid' => 'test-device-1',
            ])
            ->assertStatus(400)
            ->assertJson(['message' => 'Invalid or expired QR code']);
    }

    public function test_one_device_per_meeting_still_enforced_with_qr()
    {
        $branch = Branch::factory()->create();
        $meeting = Meeting::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'ongoing',
            'venue_lat' => 10.0,
            'venue_lng' => 10.0,
        ]);
        $user1 = User::factory()->create(['branch_id' => $branch->id]);
        $user2 = User::factory()->create(['branch_id' => $branch->id]);

        // First user marks attendance
        $token1 = $this->attendanceService->getAttendanceQrPayload($meeting);
        preg_match('/token=([^&]+)/', $token1, $matches);

        $this->actingAs($user1, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/mark-attendance", [
                'qr_token' => $matches[1],
                'lat' => 10.0,
                'lng' => 10.0,
                'device_uuid' => 'shared-device',
            ])
            ->assertStatus(200);

        // Second user tries with same device but NEW token
        $token2 = $this->attendanceService->getAttendanceQrPayload($meeting);
        preg_match('/token=([^&]+)/', $token2, $matches);

        $this->actingAs($user2, 'sanctum')
            ->postJson("/api/meetings/{$meeting->id}/mark-attendance", [
                'qr_token' => $matches[1],
                'lat' => 10.0,
                'lng' => 10.0,
                'device_uuid' => 'shared-device',
            ])
            ->assertStatus(403)
            ->assertJson(['message' => 'This device has already been used to mark attendance for another member in this meeting.']);
    }
}
