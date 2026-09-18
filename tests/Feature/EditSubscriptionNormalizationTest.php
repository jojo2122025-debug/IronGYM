<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditSubscriptionNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_subscription_accepts_plan_id_and_updates_plan_fields(): void
    {
        $member = $this->createMember('M02001');
        $oldPlan = $this->createPlan('P100', 'خطة قديمة', 120, 30);
        $newPlan = $this->createPlan('P200', 'خطة جديدة', 200, 60);

        Subscription::create([
            'id' => 'S200',
            'member_id' => $member->id,
            'plan_id' => $oldPlan->id,
            'plan_name' => $oldPlan->name,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'amount' => 120,
            'paid' => 60,
            'remaining' => 60,
            'status' => 'فعال',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->accountantUser()])
            ->postJson('/api/edit_subscription', [
                'subId' => 'S200',
                'planId' => 'P200',
                'startDate' => now()->toDateString(),
                'endDate' => now()->addDays(60)->toDateString(),
                'amount' => 200,
                'paid' => 80,
                'status' => 'فعال',
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('subscriptions', [
            'id' => 'S200',
            'plan_id' => 'P200',
            'plan_name' => 'خطة جديدة',
            'amount' => '200.00',
            'paid' => '80.00',
            'remaining' => '120.00',
        ]);
    }

    public function test_edit_subscription_accepts_legacy_plan_name_and_resolves_plan_id(): void
    {
        $member = $this->createMember('M02002');
        $oldPlan = $this->createPlan('P300', 'أساسي', 90, 30);
        $resolvedPlan = $this->createPlan('P301', 'احترافي', 150, 45);

        Subscription::create([
            'id' => 'S201',
            'member_id' => $member->id,
            'plan_id' => $oldPlan->id,
            'plan_name' => $oldPlan->name,
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDays(28)->toDateString(),
            'amount' => 90,
            'paid' => 90,
            'remaining' => 0,
            'status' => 'فعال',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->accountantUser()])
            ->postJson('/api/edit_subscription', [
                'subId' => 'S201',
                'planName' => 'احترافي',
                'startDate' => now()->toDateString(),
                'endDate' => now()->addDays(45)->toDateString(),
                'amount' => 150,
                'paid' => 50,
                'status' => 'مجمد',
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('subscriptions', [
            'id' => 'S201',
            'plan_id' => $resolvedPlan->id,
            'plan_name' => 'احترافي',
            'amount' => '150.00',
            'paid' => '50.00',
            'remaining' => '100.00',
            'status' => 'مجمد',
        ]);
    }

    private function createMember(string $id): Member
    {
        return Member::create([
            'id' => $id,
            'membership_number' => $id,
            'name' => 'عضو اختبار',
            'phone' => '0590000000',
            'gender' => 'ذكر',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPlan(string $id, string $name, float $price, int $days): Plan
    {
        return Plan::create([
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'days' => $days,
            'created_at' => now(),
        ]);
    }

    private function accountantUser(): array
    {
        return [
            'id' => 2,
            'username' => 'accountant',
            'name' => 'المحاسب',
            'role' => 'المحاسب',
            'member_id' => null,
        ];
    }
}
