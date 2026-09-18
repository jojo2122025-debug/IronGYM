<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\NutritionProgram;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\Trainer;
use App\Models\TrainerAssignment;
use App\Models\TrainingProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportsPhaseFiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_state_returns_phase_five_reports_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-06 10:00:00'));

        $admin = $this->makeUser('admin_phase5', 'مدير النظام');

        $memberA = $this->makeMember('M05001', 'عضو تقارير 1');
        $memberB = $this->makeMember('M05002', 'عضو تقارير 2');

        Subscription::create([
            'id' => 'S05001',
            'member_id' => $memberA->id,
            'plan_name' => 'اشتراك قديم',
            'start_date' => '2026-05-01',
            'end_date' => '2026-06-01',
            'amount' => 100,
            'paid' => 100,
            'remaining' => 0,
            'status' => 'منتهي',
        ]);

        Subscription::create([
            'id' => 'S05002',
            'member_id' => $memberA->id,
            'plan_name' => 'اشتراك مجدد',
            'start_date' => '2026-06-03',
            'end_date' => '2026-07-03',
            'amount' => 120,
            'paid' => 120,
            'remaining' => 0,
            'status' => 'فعال',
        ]);

        Subscription::create([
            'id' => 'S05003',
            'member_id' => $memberB->id,
            'plan_name' => 'اشتراك مع دين',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-01',
            'amount' => 200,
            'paid' => 50,
            'remaining' => 150,
            'status' => 'فعال',
        ]);

        $productA = Product::create([
            'id' => 'PRD501',
            'name' => 'بروتين',
            'price' => 50,
            'stock' => 3,
        ]);

        Product::create([
            'id' => 'PRD502',
            'name' => 'مياه',
            'price' => 5,
            'stock' => 0,
        ]);

        Product::create([
            'id' => 'PRD503',
            'name' => 'مكسرات',
            'price' => 8,
            'stock' => 20,
        ]);

        $sale = Sale::create([
            'date' => now(),
            'products' => 'بروتين x4',
            'total' => 200,
            'method' => 'نقدي',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $productA->id,
            'quantity' => 4,
            'unit_price' => 50,
            'total' => 200,
        ]);

        DB::table('checkins')->insert([
            [
                'member_id' => $memberA->id,
                'member_name' => $memberA->name,
                'time' => now()->subDay()->format('Y-m-d H:i:s'),
                'created_at' => now()->subDay(),
            ],
            [
                'member_id' => $memberB->id,
                'member_name' => $memberB->name,
                'time' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'created_at' => now()->subDays(2),
            ],
        ]);

        $trainerUser = $this->makeUser('trainer_phase5', 'مدرب');
        $trainer = Trainer::create([
            'user_id' => $trainerUser->id,
            'specialty' => 'قوة',
            'status' => 'active',
        ]);

        $assignment = TrainerAssignment::create([
            'trainer_id' => $trainer->id,
            'member_id' => $memberA->id,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        TrainingProgram::create([
            'trainer_assignment_id' => $assignment->id,
            'title' => 'برنامج قوة',
            'goal' => 'تضخيم',
            'content_json' => ['days' => 4],
        ]);

        NutritionProgram::create([
            'trainer_assignment_id' => $assignment->id,
            'title' => 'برنامج غذائي',
            'goal' => 'بناء عضلي',
            'content_json' => ['protein' => 180],
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->getJson('/api/get_state');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reports.debtsTotal', 150)
            ->assertJsonPath('data.reports.debtMembersCount', 1)
            ->assertJsonPath('data.reports.endedLast30Days', 1)
            ->assertJsonPath('data.reports.renewedLast30Days', 1)
            ->assertJsonPath('data.reports.renewalRate', 100)
            ->assertJsonCount(2, 'data.reports.stockAlerts')
            ->assertJsonPath('data.reports.topProductSales.0.productId', 'PRD501')
            ->assertJsonPath('data.reports.topProductSales.0.quantity', 4)
            ->assertJsonPath('data.reports.trainerPerformance.0.activeMembers', 1)
            ->assertJsonPath('data.reports.trainerPerformance.0.trainingPrograms', 1)
            ->assertJsonPath('data.reports.trainerPerformance.0.nutritionPrograms', 1);

        $this->assertNotEmpty($response->json('data.reports.attendanceHeatmap'));

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
