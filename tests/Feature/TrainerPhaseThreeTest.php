<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Trainer;
use App\Models\TrainerAssignment;
use App\Models\TrainingProgram;
use App\Models\NutritionProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainerPhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_trainer_profile(): void
    {
        $user = User::create([
            'username' => 'trainer_profile_user',
            'password' => bcrypt('1234'),
            'name' => 'مدرب تجريبي',
            'role' => 'موظف الاستقبال',
            'created_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/add_trainer_profile', [
                'userId' => $user->id,
                'specialty' => 'قوة ولياقة',
                'bio' => 'مدرب خبرة 5 سنوات',
                'commissionRate' => 12.5,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('trainer.user_id', $user->id);

        $this->assertDatabaseHas('trainers', [
            'user_id' => $user->id,
            'specialty' => 'قوة ولياقة',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_add_trainer_profile_by_name_and_auto_create_trainer_user(): void
    {
        $response = $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/add_trainer_profile', [
                'trainerName' => 'مدرب تلقائي',
                'specialty' => 'تحمل',
                'commissionRate' => 9,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('autoCreatedUser.name', 'مدرب تلقائي')
            ->assertJsonPath('autoCreatedUser.role', 'مدرب');

        $createdUserId = (int) $response->json('autoCreatedUser.id');

        $this->assertDatabaseHas('users', [
            'id' => $createdUserId,
            'name' => 'مدرب تلقائي',
            'role' => 'مدرب',
        ]);

        $this->assertDatabaseHas('trainers', [
            'user_id' => $createdUserId,
            'specialty' => 'تحمل',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_assign_trainer_to_member(): void
    {
        $member = Member::create([
            'id' => 'M02001',
            'membership_number' => 'M02001',
            'name' => 'عضو تجريبي',
            'phone' => '0591111111',
            'gender' => 'ذكر',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trainerUser = User::create([
            'username' => 'trainer_assign_user',
            'password' => bcrypt('1234'),
            'name' => 'مدرب الربط',
            'role' => 'موظف الاستقبال',
            'created_at' => now(),
        ]);

        $trainer = \App\Models\Trainer::create([
            'user_id' => $trainerUser->id,
            'specialty' => 'تضخيم',
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $response = $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/assign_trainer_member', [
                'trainerId' => $trainer->id,
                'memberId' => $member->id,
                'startDate' => now()->toDateString(),
                'note' => 'بداية برنامج تدريبي',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('assignment.trainer_id', $trainer->id)
            ->assertJsonPath('assignment.member_id', $member->id);

        $this->assertDatabaseHas('trainer_assignments', [
            'trainer_id' => $trainer->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);
    }

    public function test_duplicate_open_assignment_is_rejected(): void
    {
        $member = Member::create([
            'id' => 'M02002',
            'membership_number' => 'M02002',
            'name' => 'عضو مكرر',
            'phone' => '0592222222',
            'gender' => 'ذكر',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trainerUser = User::create([
            'username' => 'trainer_dup_user',
            'password' => bcrypt('1234'),
            'name' => 'مدرب التكرار',
            'role' => 'موظف الاستقبال',
            'created_at' => now(),
        ]);

        $trainer = \App\Models\Trainer::create([
            'user_id' => $trainerUser->id,
            'specialty' => 'تحمل',
            'commission_rate' => 8,
            'status' => 'active',
        ]);

        $payload = [
            'trainerId' => $trainer->id,
            'memberId' => $member->id,
            'startDate' => now()->toDateString(),
            'note' => 'ربط أول',
        ];

        $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/assign_trainer_member', $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/assign_trainer_member', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_member_cannot_be_assigned_to_two_active_trainers_with_same_specialty(): void
    {
        $member = Member::create([
            'id' => 'M02003',
            'membership_number' => 'M02003',
            'name' => 'عضو تخصص مكرر',
            'phone' => '0593333333',
            'gender' => 'ذكر',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trainerUserA = User::create([
            'username' => 'trainer_same_spec_a',
            'password' => bcrypt('1234'),
            'name' => 'مدرب تخصص A',
            'role' => 'مدرب',
            'created_at' => now(),
        ]);

        $trainerUserB = User::create([
            'username' => 'trainer_same_spec_b',
            'password' => bcrypt('1234'),
            'name' => 'مدرب تخصص B',
            'role' => 'مدرب',
            'created_at' => now(),
        ]);

        $trainerA = \App\Models\Trainer::create([
            'user_id' => $trainerUserA->id,
            'specialty' => 'قوة ولياقة',
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $trainerB = \App\Models\Trainer::create([
            'user_id' => $trainerUserB->id,
            'specialty' => 'قوة ولياقة',
            'commission_rate' => 12,
            'status' => 'active',
        ]);

        $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/assign_trainer_member', [
                'trainerId' => $trainerA->id,
                'memberId' => $member->id,
                'startDate' => '2026-06-01',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/assign_trainer_member', [
                'trainerId' => $trainerB->id,
                'memberId' => $member->id,
                'startDate' => '2026-06-02',
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'لا يمكن ربط المشترك بأكثر من مدرب نشط في نفس التخصص');
    }

    public function test_admin_can_add_training_and_nutrition_programs(): void
    {
        [$trainerUser, $trainer, $assignment] = $this->makeTrainerAssignment('M03001', 'trainer_prog_admin');

        $trainingRes = $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/add_training_program', [
                'trainerAssignmentId' => $assignment->id,
                'title' => 'برنامج قوة أساسي',
                'goal' => 'زيادة القوة خلال 8 أسابيع',
                'content' => ['notes' => 'تمارين مركبة 3 أيام أسبوعيا'],
            ]);

        $trainingRes
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('program.trainer_assignment_id', $assignment->id)
            ->assertJsonPath('program.title', 'برنامج قوة أساسي');

        $nutritionRes = $this
            ->withSession(['user' => $this->adminSession()])
            ->postJson('/api/add_nutrition_program', [
                'trainerAssignmentId' => $assignment->id,
                'title' => 'برنامج غذائي تنشيف',
                'goal' => 'خفض الدهون مع الحفاظ على العضلات',
                'content' => 'تقسيم وجبات يومي',
            ]);

        $nutritionRes
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('program.trainer_assignment_id', $assignment->id)
            ->assertJsonPath('program.title', 'برنامج غذائي تنشيف');

        $this->assertDatabaseHas('training_programs', [
            'trainer_assignment_id' => $assignment->id,
            'title' => 'برنامج قوة أساسي',
        ]);

        $this->assertDatabaseHas('nutrition_programs', [
            'trainer_assignment_id' => $assignment->id,
            'title' => 'برنامج غذائي تنشيف',
        ]);
    }

    public function test_trainer_can_manage_own_programs_only(): void
    {
        [$trainerUserA, $trainerA, $assignmentA] = $this->makeTrainerAssignment('M03002', 'trainer_prog_owner_a');
        [$trainerUserB, $trainerB, $assignmentB] = $this->makeTrainerAssignment('M03003', 'trainer_prog_owner_b');

        $trainingProgram = TrainingProgram::create([
            'trainer_assignment_id' => $assignmentA->id,
            'title' => 'برنامج A',
            'goal' => 'هدف A',
            'content_json' => ['notes' => 'A'],
        ]);

        $nutritionProgram = NutritionProgram::create([
            'trainer_assignment_id' => $assignmentA->id,
            'title' => 'نظام A',
            'goal' => 'هدف غذائي A',
            'content_json' => ['notes' => 'A'],
        ]);

        // Owner trainer can edit own program.
        $this
            ->withSession(['user' => $this->trainerSession($trainerUserA->id, $trainerUserA->username, $trainerUserA->name)])
            ->postJson('/api/edit_training_program', [
                'id' => $trainingProgram->id,
                'trainerAssignmentId' => $assignmentA->id,
                'title' => 'برنامج A محدث',
                'goal' => 'هدف محدث',
                'content' => ['notes' => 'updated'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Different trainer cannot edit other's program.
        $this
            ->withSession(['user' => $this->trainerSession($trainerUserB->id, $trainerUserB->username, $trainerUserB->name)])
            ->postJson('/api/edit_training_program', [
                'id' => $trainingProgram->id,
                'trainerAssignmentId' => $assignmentA->id,
                'title' => 'اختراق',
                'goal' => 'غير مصرح',
                'content' => 'x',
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        // Different trainer cannot delete other's nutrition program.
        $this
            ->withSession(['user' => $this->trainerSession($trainerUserB->id, $trainerUserB->username, $trainerUserB->name)])
            ->postJson('/api/delete_nutrition_program', [
                'id' => $nutritionProgram->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        // Owner trainer can delete own nutrition program.
        $this
            ->withSession(['user' => $this->trainerSession($trainerUserA->id, $trainerUserA->username, $trainerUserA->name)])
            ->postJson('/api/delete_nutrition_program', [
                'id' => $nutritionProgram->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('training_programs', [
            'id' => $trainingProgram->id,
            'title' => 'برنامج A محدث',
        ]);

        $this->assertDatabaseMissing('nutrition_programs', [
            'id' => $nutritionProgram->id,
        ]);
    }

    private function makeTrainerAssignment(string $memberId, string $usernamePrefix): array
    {
        $member = Member::create([
            'id' => $memberId,
            'membership_number' => $memberId,
            'name' => 'عضو ' . $memberId,
            'phone' => '059' . substr(preg_replace('/\D+/', '', $memberId), -7),
            'gender' => 'ذكر',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trainerUser = User::create([
            'username' => $usernamePrefix,
            'password' => bcrypt('1234'),
            'name' => 'مدرب ' . $memberId,
            'role' => 'مدرب',
            'created_at' => now(),
        ]);

        $trainer = Trainer::create([
            'user_id' => $trainerUser->id,
            'specialty' => 'قوة ولياقة',
            'commission_rate' => 10,
            'status' => 'active',
        ]);

        $assignment = TrainerAssignment::create([
            'trainer_id' => $trainer->id,
            'member_id' => $member->id,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        return [$trainerUser, $trainer, $assignment];
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

    private function trainerSession(int $id, string $username, string $name): array
    {
        return [
            'id' => $id,
            'username' => $username,
            'name' => $name,
            'role' => 'مدرب',
            'member_id' => null,
        ];
    }
}
