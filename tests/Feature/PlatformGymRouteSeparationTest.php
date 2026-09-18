<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformGymRouteSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_login_accepts_only_platform_admins(): void
    {
        $adminPassword = Str::password(32);
        $gymPassword = Str::password(32);

        User::create([
            'username' => 'admin',
            'name' => 'مدير النظام',
            'password' => Hash::make($adminPassword),
            'role' => 'مدير النظام',
        ]);

        User::create([
            'username' => 'gymmanager',
            'name' => 'مدير الصالة',
            'password' => Hash::make($gymPassword),
            'role' => 'مدير الصالة',
        ]);

        $adminResponse = $this->postJson('/api/platform/login', [
            'username' => 'admin',
            'password' => $adminPassword,
        ]);

        $adminResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'مدير النظام');

        $gymManagerResponse = $this->postJson('/api/platform/login', [
            'username' => 'gymmanager',
            'password' => $gymPassword,
        ]);

        $gymManagerResponse
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'هذا المسار مخصص لمشرف المنصة فقط');
    }

    public function test_gym_login_accepts_gym_staff_but_blocks_platform_admins(): void
    {
        $adminPassword = Str::password(32);
        $gymPassword = Str::password(32);

        User::create([
            'username' => 'gymmanager',
            'name' => 'مدير الصالة',
            'password' => Hash::make($gymPassword),
            'role' => 'مدير الصالة',
        ]);

        User::create([
            'username' => 'admin',
            'name' => 'مدير النظام',
            'password' => Hash::make($adminPassword),
            'role' => 'مدير النظام',
        ]);

        $gymManagerResponse = $this->postJson('/api/gym/login', [
            'username' => 'gymmanager',
            'password' => $gymPassword,
        ]);

        $gymManagerResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'مدير الصالة');

        $adminResponse = $this->postJson('/api/gym/login', [
            'username' => 'admin',
            'password' => $adminPassword,
        ]);

        $adminResponse
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'هذا المسار مخصص لطاقم الصالة فقط');
    }
}
