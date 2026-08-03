<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_login_with_master_password()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'membership_number' => '12345',
            'password' => bcrypt('correct_password'),
            'approval_status' => 'approved',
            'is_admin' => false,
        ]);

        // Attempt login with wrong password (should fail)
        $response = $this->postJson('/api/login', [
            'branch_id' => $branch->id,
            'membership_number' => '12345',
            'password' => 'wrong_password',
        ]);
        $response->assertStatus(422);

        // Attempt login with master password (should succeed after implementation)
        $response = $this->postJson('/api/login', [
            'branch_id' => $branch->id,
            'membership_number' => '12345',
            'password' => '123456',
        ]);

        // This will currently fail until we implement the master password
        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
    }
}
