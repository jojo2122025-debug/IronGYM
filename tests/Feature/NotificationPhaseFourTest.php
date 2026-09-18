<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPhaseFourTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_template_and_send_notification_from_it(): void
    {
        $admin = $this->makeUser('admin_phase4', 'مدير النظام');
        $member = $this->makeMember('M04001', 'مشترك إشعار');

        $templateResponse = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/add_notification_template', [
                'name' => 'تذكير تجريبي',
                'channel' => 'whatsapp',
                'type' => 'manual_test',
                'titleTemplate' => 'تنبيه إلى {member_name}',
                'bodyTemplate' => 'مرحباً {member_name} ({member_id})',
                'isActive' => true,
            ]);

        $templateResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('template.type', 'manual_test');

        $templateId = (int) $templateResponse->json('template.id');

        $sendResponse = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/send_notification', [
                'templateId' => $templateId,
                'memberId' => $member->id,
            ]);

        $sendResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('notification.status', 'sent')
            ->assertJsonPath('notification.type', 'manual_test');

        $this->assertDatabaseHas('notifications', [
            'member_id' => $member->id,
            'template_id' => $templateId,
            'type' => 'manual_test',
            'status' => 'sent',
            'title' => 'تنبيه إلى مشترك إشعار',
        ]);
    }

    public function test_process_notification_jobs_creates_due_reminders_without_daily_duplicates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-06 09:00:00'));

        $admin = $this->makeUser('admin_jobs_phase4', 'مدير النظام');

        NotificationTemplate::create([
            'name' => 'قبل 3 أيام',
            'channel' => 'whatsapp',
            'type' => 'subscription_expiring_3_days',
            'title_template' => 'سينتهي الاشتراك قريباً',
            'body_template' => 'ينتهي اشتراك {member_name} بتاريخ {end_date}',
            'is_active' => true,
        ]);

        NotificationTemplate::create([
            'name' => 'اليوم',
            'channel' => 'whatsapp',
            'type' => 'subscription_expires_today',
            'title_template' => 'اشتراك اليوم',
            'body_template' => 'اشتراك {member_name} ينتهي اليوم {end_date}',
            'is_active' => true,
        ]);

        NotificationTemplate::create([
            'name' => 'منتهي',
            'channel' => 'whatsapp',
            'type' => 'subscription_expired',
            'title_template' => 'اشتراك منتهي',
            'body_template' => 'انتهى اشتراك {member_name} بتاريخ {end_date}',
            'is_active' => true,
        ]);

        $memberThreeDays = $this->makeMember('M04002', 'عضو 3 أيام');
        $memberToday = $this->makeMember('M04003', 'عضو اليوم');
        $memberExpired = $this->makeMember('M04004', 'عضو منتهي');

        Subscription::create([
            'id' => 'S04002',
            'member_id' => $memberThreeDays->id,
            'plan_name' => 'اشتراك شهر',
            'start_date' => '2026-05-10',
            'end_date' => '2026-06-09',
            'amount' => 200,
            'paid' => 200,
            'remaining' => 0,
            'status' => 'فعال',
        ]);

        Subscription::create([
            'id' => 'S04003',
            'member_id' => $memberToday->id,
            'plan_name' => 'اشتراك شهر',
            'start_date' => '2026-05-07',
            'end_date' => '2026-06-06',
            'amount' => 200,
            'paid' => 150,
            'remaining' => 50,
            'status' => 'فعال',
        ]);

        Subscription::create([
            'id' => 'S04004',
            'member_id' => $memberExpired->id,
            'plan_name' => 'اشتراك شهر',
            'start_date' => '2026-05-01',
            'end_date' => '2026-06-01',
            'amount' => 200,
            'paid' => 100,
            'remaining' => 100,
            'status' => 'منتهي',
        ]);

        $firstRun = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/process_notification_jobs');

        $firstRun
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.expiring_3_days', 1)
            ->assertJsonPath('summary.expires_today', 1)
            ->assertJsonPath('summary.expired', 1);

        $this->assertSame(3, Notification::count());

        $secondRun = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/process_notification_jobs');

        $secondRun
            ->assertOk()
            ->assertJsonPath('summary.expiring_3_days', 0)
            ->assertJsonPath('summary.expires_today', 0)
            ->assertJsonPath('summary.expired', 0);

        $this->assertSame(3, Notification::count());

        Carbon::setTestNow();
    }

    private function makeMember(string $id, string $name): Member
    {
        return Member::create([
            'id' => $id,
            'membership_number' => $id,
            'name' => $name,
            'phone' => '0590000000',
            'gender' => 'ذكر',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $username, string $role): User
    {
        return User::create([
            'username' => $username,
            'password' => bcrypt('1234'),
            'name' => $username,
            'role' => $role,
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
        ];
    }
}
