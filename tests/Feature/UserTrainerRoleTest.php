<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTrainerRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_user_with_trainer_role(): void
    {
        $response = $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/add_user', [
                'username' => 'trainer_role_test',
                'password' => 'Pass@1234',
                'name' => 'مستخدم مدرب',
                'role' => 'مدرب',
                'memberId' => null,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'username' => 'trainer_role_test',
            'role' => 'مدرب',
            'name' => 'مستخدم مدرب',
        ]);
    }

    private function adminSession(): array
    {
        return [
            'id' => 1,
            'username' => 'admin',
            'name' => 'مدير النظام',
            'role' => 'مدير النظام',
            'member_id' => null,
        ];
    }
}
