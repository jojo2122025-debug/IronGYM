<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\Member;
use App\Models\MembershipCard;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GateCheckinTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_check_in_with_active_card_code(): void
    {
        $member = $this->createMemberWithCard('M01001', 'GYM-M01001');
        $plan = Plan::create([
            'id' => 'P900',
            'name' => 'اختبار شهر',
            'price' => 100,
            'days' => 30,
            'created_at' => now(),
        ]);

        Subscription::create([
            'id' => 'S900',
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'amount' => 100,
            'paid' => 100,
            'remaining' => 0,
            'status' => 'فعال',
            'created_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->receptionUser()])
            ->postJson('/api/scan_check_in', ['code' => 'GYM-M01001']);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('member.id', 'M01001')
            ->assertJsonPath('member.subscriptionStatus', 'فعال');

        $this->assertDatabaseHas('checkins', [
            'member_id' => 'M01001',
            'subscription_id' => 'S900',
            'source' => 'qr',
            'status' => 'allowed',
        ]);
    }

    public function test_member_without_active_subscription_is_denied_at_gate(): void
    {
        $this->createMemberWithCard('M01002', 'GYM-M01002');

        $response = $this
            ->withSession(['user' => $this->receptionUser()])
            ->postJson('/api/scan_check_in', ['code' => 'GYM-M01002']);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('member.id', 'M01002')
            ->assertJsonPath('member.subscriptionStatus', 'غير فعال');

        $this->assertDatabaseHas('checkins', [
            'member_id' => 'M01002',
            'source' => 'qr',
            'status' => 'denied',
            'denial_reason' => 'لا يوجد اشتراك فعال',
        ]);
    }

    public function test_member_can_check_out_with_card_code(): void
    {
        $member = $this->createMemberWithCard('M01003', 'GYM-M01003');
        Checkin::create([
            'member_id' => $member->id,
            'member_name' => $member->name,
            'time' => '08:00 ص',
            'source' => 'qr',
            'status' => 'allowed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->receptionUser()])
            ->postJson('/api/check_out', ['code' => 'GYM-M01003']);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('member.id', 'M01003');

        $this->assertNotNull(Checkin::where('member_id', 'M01003')->first()?->checkout_at);
    }

    public function test_reissue_membership_card_revokes_old_active_card_and_creates_new_one(): void
    {
        $member = $this->createMemberWithCard('M01004', 'GYM-M01004');

        $response = $this
            ->withSession(['user' => $this->receptionUser()])
            ->postJson('/api/issue_membership_card', ['memberId' => $member->id]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('memberId', 'M01004');

        $newCode = (string) $response->json('cardCode');
        $this->assertNotEmpty($newCode);
        $this->assertNotSame('GYM-M01004', $newCode);

        $this->assertDatabaseHas('membership_cards', [
            'member_id' => 'M01004',
            'code' => 'GYM-M01004',
            'status' => 'revoked',
        ]);

        $this->assertDatabaseHas('membership_cards', [
            'member_id' => 'M01004',
            'code' => $newCode,
            'status' => 'active',
            'type' => 'qr',
        ]);
    }

    private function createMemberWithCard(string $memberId, string $cardCode): Member
    {
        $member = Member::create([
            'id' => $memberId,
            'membership_number' => $memberId,
            'name' => 'مشترك اختبار',
            'phone' => '0590000000',
            'gender' => 'ذكر',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MembershipCard::create([
            'member_id' => $member->id,
            'code' => $cardCode,
            'type' => 'qr',
            'status' => 'active',
            'issued_at' => now(),
        ]);

        return $member;
    }

    private function receptionUser(): array
    {
        return [
            'id' => 1,
            'username' => 'receptionist',
            'name' => 'موظف الاستقبال',
            'role' => 'موظف الاستقبال',
            'member_id' => null,
        ];
    }
}
