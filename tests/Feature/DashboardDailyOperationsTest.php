<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardDailyOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_contains_daily_overview_and_quick_actions(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('dashboard-today-title')
            ->assertSee('dash-currently-inside')
            ->assertSee('dash-net-today')
            ->assertSee('dash-refresh')
            ->assertSee('data-dashboard-action="payment"', false);
    }

    public function test_dashboard_counts_only_todays_valid_activity_and_finances(): void
    {
        Carbon::setTestNow('2026-09-23 15:00:00');

        DB::table('members')->insert([
            ['id' => 'M00001', 'name' => 'أحمد', 'phone' => '0591000001', 'gender' => 'ذكر', 'created_at' => '2026-09-23 09:00:00'],
            ['id' => 'M00002', 'name' => 'سارة', 'phone' => '0591000002', 'gender' => 'أنثى', 'created_at' => '2026-09-22 09:00:00'],
        ]);
        DB::table('checkins')->insert([
            ['member_id' => 'M00001', 'member_name' => 'أحمد', 'time' => '10:00', 'status' => 'allowed', 'created_at' => '2026-09-23 10:00:00', 'checkout_at' => null],
            ['member_id' => 'M00002', 'member_name' => 'سارة', 'time' => '11:00', 'status' => 'allowed', 'created_at' => '2026-09-23 11:00:00', 'checkout_at' => '2026-09-23 12:00:00'],
            ['member_id' => 'M00002', 'member_name' => 'سارة', 'time' => '13:00', 'status' => 'denied', 'created_at' => '2026-09-23 13:00:00', 'checkout_at' => null],
            ['member_id' => 'M00001', 'member_name' => 'أحمد', 'time' => '09:00', 'status' => 'allowed', 'created_at' => '2026-09-22 09:00:00', 'checkout_at' => null],
        ]);
        DB::table('payments')->insert([
            ['id' => 'PAY001', 'member_name' => 'أحمد', 'date' => '2026-09-23 10:00:00', 'amount' => 100, 'method' => 'نقدي'],
            ['id' => 'PAY002', 'member_name' => 'سارة', 'date' => '2026-09-23 11:00:00', 'amount' => 50, 'method' => 'تحويل'],
            ['id' => 'PAY003', 'member_name' => 'أحمد', 'date' => '2026-09-22 10:00:00', 'amount' => 1000, 'method' => 'نقدي'],
        ]);
        DB::table('expenses')->insert([
            ['date' => '2026-09-23 12:00:00', 'category' => 'كهرباء', 'amount' => 30, 'method' => 'نقدي'],
            ['date' => '2026-09-22 12:00:00', 'category' => 'مياه', 'amount' => 200, 'method' => 'نقدي'],
        ]);
        DB::table('subscriptions')->insert([
            ['id' => 'S00001', 'member_id' => 'M00001', 'plan_name' => 'شهري', 'start_date' => '2026-09-01', 'end_date' => '2026-09-25', 'amount' => 100, 'paid' => 100, 'remaining' => 0, 'status' => 'فعال'],
            ['id' => 'S00002', 'member_id' => 'M00001', 'plan_name' => 'آخر', 'start_date' => '2026-09-01', 'end_date' => '2026-09-26', 'amount' => 100, 'paid' => 100, 'remaining' => 0, 'status' => 'فعال'],
        ]);

        $response = $this->withSession(['user' => ['id' => 1, 'name' => 'مدير', 'role' => 'مدير النظام']])
            ->getJson('/api/get_state');

        $response->assertOk()
            ->assertJsonPath('data.todayAttendance', 2)
            ->assertJsonPath('data.dailyOperations.attendanceToday', 2)
            ->assertJsonPath('data.dailyOperations.currentlyInside', 1)
            ->assertJsonPath('data.dailyOperations.paymentsToday', 2)
            ->assertJsonPath('data.dailyOperations.revenueToday', 150)
            ->assertJsonPath('data.dailyOperations.cashToday', 100)
            ->assertJsonPath('data.dailyOperations.transferToday', 50)
            ->assertJsonPath('data.dailyOperations.expensesToday', 30)
            ->assertJsonPath('data.dailyOperations.netToday', 120)
            ->assertJsonPath('data.dailyOperations.newMembersToday', 1)
            ->assertJsonPath('data.dailyOperations.expiringMembers', 1)
            ->assertJsonPath('data.peakHours.10', 1)
            ->assertJsonPath('data.peakHours.11', 1)
            ->assertJsonPath('data.peakHours.13', 0);
    }
}
