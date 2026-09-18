<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaaSPhaseSevenTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_state_includes_tenant_payload(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->getJson('/api/get_state');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.selectedGymId', 1)
            ->assertJsonPath('data.tenant.selectedBranchId', 1)
            ->assertJsonStructure([
                'data' => [
                    'tenant' => [
                        'gyms',
                        'selectedGymId',
                        'selectedBranchId',
                    ],
                ],
            ]);
    }

    public function test_admin_can_select_branch_and_session_updates(): void
    {
        $admin = $this->makeAdminUser();
        $gym = Gym::create([
            'name' => 'Gym Two',
            'code' => 'gym-two',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);
        $branch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch Two',
            'code' => 'branch-two',
            'status' => 'active',
            'address' => 'Main street',
            'phone' => '0599999999',
            'is_default' => true,
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/select_branch', [
                'branchId' => $branch->id,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('branch.id', $branch->id)
            ->assertJsonPath('branch.gym.id', $gym->id);

        $this->assertSame($gym->id, session('user.gym_id'));
        $this->assertSame($branch->id, session('user.branch_id'));
    }

    public function test_admin_can_add_gym_and_default_branch(): void
    {
        $admin = $this->makeAdminUser();

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/add_gym', [
                'name' => 'Gym Three',
                'code' => 'gym-three',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('gym.code', 'gym-three')
            ->assertJsonPath('defaultBranch.code', 'main');

        $gymId = (int) $response->json('gym.id');

        $this->assertDatabaseHas('gyms', [
            'id' => $gymId,
            'name' => 'Gym Three',
            'code' => 'gym-three',
        ]);

        $this->assertDatabaseHas('branches', [
            'gym_id' => $gymId,
            'code' => 'main',
            'is_default' => 1,
        ]);
    }

    public function test_admin_can_add_branch_to_existing_gym(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Four',
            'code' => 'gym-four',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/add_branch', [
                'gymId' => $gym->id,
                'name' => 'Branch Four A',
                'code' => 'four-a',
                'address' => 'Street 4',
                'phone' => '0594444444',
                'isDefault' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('branch.gym_id', $gym->id)
            ->assertJsonPath('branch.code', 'four-a')
            ->assertJsonPath('branch.is_default', true);

        $this->assertDatabaseHas('branches', [
            'gym_id' => $gym->id,
            'name' => 'Branch Four A',
            'code' => 'four-a',
            'is_default' => 1,
        ]);
    }

    public function test_cannot_add_branch_to_inactive_gym(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Inactive Add Branch',
            'code' => 'gym-inactive-add-branch',
            'status' => 'inactive',
            'settings' => ['currency' => 'ILS'],
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/add_branch', [
                'gymId' => $gym->id,
                'name' => 'Branch Should Fail',
                'code' => 'fail-branch',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'لا يمكن إضافة فرع لصالة غير نشطة');

        $this->assertDatabaseMissing('branches', [
            'gym_id' => $gym->id,
            'code' => 'fail-branch',
        ]);
    }

    public function test_admin_can_update_gym_and_branch_details(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Edit',
            'code' => 'gym-edit',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch Edit',
            'code' => 'branch-edit',
            'status' => 'active',
            'is_default' => true,
        ]);

        $fallbackBranch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch Edit Fallback',
            'code' => 'branch-edit-fallback',
            'status' => 'active',
            'is_default' => false,
        ]);

        $updateBranch = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/update_branch', [
                'branchId' => $branch->id,
                'name' => 'Branch Edit Updated',
                'code' => 'branch-edit-updated',
                'address' => 'New Address',
                'phone' => '0591231231',
                'status' => 'inactive',
            ]);

        $updateBranch
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('branch.name', 'Branch Edit Updated')
            ->assertJsonPath('branch.code', 'branch-edit-updated')
            ->assertJsonPath('branch.status', 'inactive');

        $updateGym = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/update_gym', [
                'gymId' => $gym->id,
                'name' => 'Gym Edit Updated',
                'status' => 'inactive',
            ]);

        $updateGym
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('gym.name', 'Gym Edit Updated')
            ->assertJsonPath('gym.status', 'inactive')
            ->assertJsonPath('reassigned', false);

        $this->assertDatabaseHas('gyms', [
            'id' => $gym->id,
            'name' => 'Gym Edit Updated',
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Branch Edit Updated',
            'code' => 'branch-edit-updated',
            'status' => 'inactive',
            'address' => 'New Address',
            'phone' => '0591231231',
            'is_default' => 0,
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $fallbackBranch->id,
            'status' => 'inactive',
            'is_default' => 0,
        ]);
    }

    public function test_update_branch_cannot_activate_when_parent_gym_is_inactive(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Update Inactive Parent',
            'code' => 'gym-update-inactive-parent',
            'status' => 'inactive',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch Update Inactive Parent',
            'code' => 'branch-update-inactive-parent',
            'status' => 'inactive',
            'is_default' => false,
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/update_branch', [
                'branchId' => $branch->id,
                'name' => 'Branch Update Inactive Parent',
                'code' => 'branch-update-inactive-parent',
                'status' => 'active',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'لا يمكن تفعيل فرع داخل صالة غير نشطة');

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'status' => 'inactive',
        ]);
    }

    public function test_update_branch_cannot_deactivate_last_active_branch(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Update Last Active',
            'code' => 'gym-update-last-active',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Only Active Branch',
            'code' => 'only-active-branch',
            'status' => 'active',
            'is_default' => true,
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/update_branch', [
                'branchId' => $branch->id,
                'name' => 'Only Active Branch',
                'code' => 'only-active-branch',
                'status' => 'inactive',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'لا يمكن تعطيل آخر فرع نشط في الصالة');

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'status' => 'active',
            'is_default' => 1,
        ]);
    }

    public function test_deactivating_gym_auto_deactivates_all_branches(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Guard',
            'code' => 'gym-guard',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Guard Branch Active',
            'code' => 'guard-active',
            'status' => 'active',
            'is_default' => true,
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/toggle_gym_status', [
                'gymId' => $gym->id,
                'status' => 'inactive',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('gym.status', 'inactive')
            ->assertJsonPath('reassigned', false);

        $this->assertDatabaseHas('gyms', [
            'id' => $gym->id,
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'status' => 'inactive',
            'is_default' => 0,
        ]);
    }

    public function test_cannot_deactivate_current_gym_without_fallback_active_branch(): void
    {
        $gym = Gym::create([
            'name' => 'Gym Current Only',
            'code' => 'gym-current-only',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Current Branch',
            'code' => 'current-branch',
            'status' => 'active',
            'is_default' => true,
        ]);

        Branch::query()->where('id', '!=', $branch->id)->update([
            'status' => 'inactive',
            'is_default' => false,
        ]);

        $admin = User::create([
            'username' => 'admin_current_only_phase7',
            'password' => bcrypt('1234'),
            'name' => 'Admin Current Only',
            'role' => 'مدير النظام',
            'gym_id' => $gym->id,
            'branch_id' => $branch->id,
            'created_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/toggle_gym_status', [
                'gymId' => $gym->id,
                'status' => 'inactive',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'لا يمكن تعطيل الصالة الحالية لعدم توفر فرع نشط بديل');

        $this->assertDatabaseHas('gyms', [
            'id' => $gym->id,
            'status' => 'active',
        ]);
    }

    public function test_deactivating_current_gym_reassigns_user_to_fallback_branch(): void
    {
        $gymA = Gym::create([
            'name' => 'Gym A Current',
            'code' => 'gym-a-current',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branchA = Branch::create([
            'gym_id' => $gymA->id,
            'name' => 'Branch A Current',
            'code' => 'branch-a-current',
            'status' => 'active',
            'is_default' => true,
        ]);

        $gymB = Gym::create([
            'name' => 'Gym B Fallback',
            'code' => 'gym-b-fallback',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branchB = Branch::create([
            'gym_id' => $gymB->id,
            'name' => 'Branch B Fallback',
            'code' => 'branch-b-fallback',
            'status' => 'active',
            'is_default' => true,
        ]);

        Branch::query()->whereNotIn('id', [$branchA->id, $branchB->id])->update([
            'status' => 'inactive',
            'is_default' => false,
        ]);

        $admin = User::create([
            'username' => 'admin_reassign_phase7',
            'password' => bcrypt('1234'),
            'name' => 'Admin Reassign',
            'role' => 'مدير النظام',
            'gym_id' => $gymA->id,
            'branch_id' => $branchA->id,
            'created_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/toggle_gym_status', [
                'gymId' => $gymA->id,
                'status' => 'inactive',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('gym.status', 'inactive')
            ->assertJsonPath('reassigned', true)
            ->assertJsonPath('reassignedBranch.gym_id', $gymB->id)
            ->assertJsonPath('reassignedBranch.branch_id', $branchB->id);

        $this->assertSame($gymB->id, session('user.gym_id'));
        $this->assertSame($branchB->id, session('user.branch_id'));

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'gym_id' => $gymB->id,
            'branch_id' => $branchB->id,
        ]);
    }

    public function test_update_gym_returns_reassignment_metadata_for_current_gym(): void
    {
        $gymA = Gym::create([
            'name' => 'Gym A Update Current',
            'code' => 'gym-a-update-current',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branchA = Branch::create([
            'gym_id' => $gymA->id,
            'name' => 'Branch A Update Current',
            'code' => 'branch-a-update-current',
            'status' => 'active',
            'is_default' => true,
        ]);

        $gymB = Gym::create([
            'name' => 'Gym B Update Fallback',
            'code' => 'gym-b-update-fallback',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branchB = Branch::create([
            'gym_id' => $gymB->id,
            'name' => 'Branch B Update Fallback',
            'code' => 'branch-b-update-fallback',
            'status' => 'active',
            'is_default' => true,
        ]);

        Branch::query()->whereNotIn('id', [$branchA->id, $branchB->id])->update([
            'status' => 'inactive',
            'is_default' => false,
        ]);

        $admin = User::create([
            'username' => 'admin_reassign_update_phase7',
            'password' => bcrypt('1234'),
            'name' => 'Admin Reassign Update',
            'role' => 'مدير النظام',
            'gym_id' => $gymA->id,
            'branch_id' => $branchA->id,
            'created_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/update_gym', [
                'gymId' => $gymA->id,
                'name' => 'Gym A Update Current Renamed',
                'status' => 'inactive',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('gym.status', 'inactive')
            ->assertJsonPath('reassigned', true)
            ->assertJsonPath('reassignedBranch.gym_id', $gymB->id)
            ->assertJsonPath('reassignedBranch.branch_id', $branchB->id);

        $this->assertSame($gymB->id, session('user.gym_id'));
        $this->assertSame($branchB->id, session('user.branch_id'));
    }

    public function test_admin_can_toggle_gym_status_when_no_active_branches(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Toggle Status',
            'code' => 'gym-toggle-status',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Inactive Branch One',
            'code' => 'inactive-one',
            'status' => 'inactive',
            'is_default' => false,
        ]);

        $deactivate = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/toggle_gym_status', [
                'gymId' => $gym->id,
                'status' => 'inactive',
            ]);

        $deactivate
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('gym.id', $gym->id)
            ->assertJsonPath('gym.status', 'inactive');

        $activate = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/toggle_gym_status', [
                'gymId' => $gym->id,
                'status' => 'active',
            ]);

        $activate
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('gym.id', $gym->id)
            ->assertJsonPath('gym.status', 'active');
    }

    public function test_admin_can_set_default_branch_for_gym(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Default',
            'code' => 'gym-default',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branchA = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch A',
            'code' => 'branch-a',
            'status' => 'active',
            'is_default' => true,
        ]);

        $branchB = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch B',
            'code' => 'branch-b',
            'status' => 'active',
            'is_default' => false,
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/set_default_branch', [
                'branchId' => $branchB->id,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('branch.id', $branchB->id)
            ->assertJsonPath('branch.is_default', true);

        $this->assertDatabaseHas('branches', [
            'id' => $branchA->id,
            'is_default' => 0,
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branchB->id,
            'is_default' => 1,
        ]);
    }

    public function test_cannot_set_inactive_branch_as_default(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Default Guard',
            'code' => 'gym-default-guard',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $activeDefault = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Active Default',
            'code' => 'active-default',
            'status' => 'active',
            'is_default' => true,
        ]);

        $inactiveBranch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Inactive Branch',
            'code' => 'inactive-branch',
            'status' => 'inactive',
            'is_default' => false,
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/set_default_branch', [
                'branchId' => $inactiveBranch->id,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'لا يمكن تعيين فرع غير نشط كافتراضي');

        $this->assertDatabaseHas('branches', [
            'id' => $activeDefault->id,
            'is_default' => 1,
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $inactiveBranch->id,
            'is_default' => 0,
        ]);
    }

    public function test_admin_can_toggle_branch_status_with_safety_rules(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Toggle',
            'code' => 'gym-toggle',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branchA = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch Toggle A',
            'code' => 'toggle-a',
            'status' => 'active',
            'is_default' => true,
        ]);

        $branchB = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch Toggle B',
            'code' => 'toggle-b',
            'status' => 'active',
            'is_default' => false,
        ]);

        $deactivateA = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/toggle_branch_status', [
                'branchId' => $branchA->id,
                'status' => 'inactive',
            ]);

        $deactivateA
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('branch.id', $branchA->id)
            ->assertJsonPath('branch.status', 'inactive');

        $this->assertDatabaseHas('branches', [
            'id' => $branchA->id,
            'status' => 'inactive',
            'is_default' => 0,
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branchB->id,
            'status' => 'active',
            'is_default' => 1,
        ]);

        $deactivateB = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/toggle_branch_status', [
                'branchId' => $branchB->id,
                'status' => 'inactive',
            ]);

        $deactivateB
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_activate_branch_when_parent_gym_is_inactive(): void
    {
        $admin = $this->makeAdminUser();

        $gym = Gym::create([
            'name' => 'Gym Inactive Parent',
            'code' => 'gym-inactive-parent',
            'status' => 'inactive',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch Inactive Parent',
            'code' => 'branch-inactive-parent',
            'status' => 'inactive',
            'is_default' => false,
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/toggle_branch_status', [
                'branchId' => $branch->id,
                'status' => 'active',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'لا يمكن تفعيل فرع داخل صالة غير نشطة');

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'status' => 'inactive',
        ]);
    }

    public function test_cannot_select_branch_when_parent_gym_is_inactive(): void
    {
        $gym = Gym::create([
            'name' => 'Gym Inactive Select',
            'code' => 'gym-inactive-select',
            'status' => 'inactive',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branch = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch Inactive Select',
            'code' => 'branch-inactive-select',
            'status' => 'active',
            'is_default' => true,
        ]);

        $admin = User::create([
            'username' => 'admin_inactive_select_phase7',
            'password' => bcrypt('1234'),
            'name' => 'Admin Inactive Select',
            'role' => 'مدير النظام',
            'gym_id' => null,
            'branch_id' => null,
            'created_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/select_branch', [
                'branchId' => $branch->id,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'الصالة غير نشطة');
    }

    public function test_tenant_management_actions_are_logged_in_activity_log(): void
    {
        $admin = $this->makeAdminUser();

        $gymResponse = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/add_gym', [
                'name' => 'Gym Logged',
                'code' => 'gym-logged',
            ]);

        $gymResponse
            ->assertOk()
            ->assertJsonPath('success', true);

        $gymId = (int) $gymResponse->json('gym.id');
        $branchId = (int) $gymResponse->json('defaultBranch.id');

        $setDefaultResponse = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/set_default_branch', [
                'branchId' => $branchId,
            ]);

        $setDefaultResponse
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('activity_log', [
            'action' => 'إضافة صالة',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'action' => 'تعيين فرع افتراضي',
        ]);
    }

    public function test_branch_scope_is_applied_to_new_and_loaded_members(): void
    {
        $gym = Gym::create([
            'name' => 'Gym Scope',
            'code' => 'gym-scope',
            'status' => 'active',
            'settings' => ['currency' => 'ILS'],
        ]);

        $branchA = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch A',
            'code' => 'a',
            'status' => 'active',
            'is_default' => true,
        ]);

        $branchB = Branch::create([
            'gym_id' => $gym->id,
            'name' => 'Branch B',
            'code' => 'b',
            'status' => 'active',
            'is_default' => false,
        ]);

        $admin = User::create([
            'username' => 'admin_scope_phase7',
            'password' => bcrypt('1234'),
            'name' => 'Admin Scope',
            'role' => 'مدير النظام',
            'gym_id' => $gym->id,
            'branch_id' => $branchA->id,
            'created_at' => now(),
        ]);

        $addMember = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/add_member', [
                'name' => 'Member Branch A',
                'phone' => '0591111111',
                'gender' => 'ذكر',
                'whatsapp' => '0591111111',
            ]);

        $addMember
            ->assertOk()
            ->assertJsonPath('success', true);

        $memberId = $addMember->json('id');

        $this->assertDatabaseHas('members', [
            'id' => $memberId,
            'branch_id' => $branchA->id,
            'gym_id' => $gym->id,
        ]);

        $stateA = $this
            ->withSession(['user' => [
                ...$this->sessionUser($admin),
                'branch_id' => $branchA->id,
                'gym_id' => $gym->id,
            ]])
            ->getJson('/api/get_state');

        $stateB = $this
            ->withSession(['user' => [
                ...$this->sessionUser($admin),
                'branch_id' => $branchB->id,
                'gym_id' => $gym->id,
            ]])
            ->getJson('/api/get_state');

        $membersA = collect($stateA->json('data.members'))->pluck('id')->all();
        $membersB = collect($stateB->json('data.members'))->pluck('id')->all();

        $this->assertContains($memberId, $membersA);
        $this->assertNotContains($memberId, $membersB);
    }

    private function makeAdminUser(): User
    {
        return User::create([
            'username' => 'admin_saas_phase7',
            'password' => bcrypt('1234'),
            'name' => 'مدير النظام',
            'role' => 'مدير النظام',
            'created_at' => now(),
        ]);
    }

    private function sessionUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role' => $user->role,
            'member_id' => $user->member_id,
            'gym_id' => $user->gym_id,
            'branch_id' => $user->branch_id,
        ];
    }
}
