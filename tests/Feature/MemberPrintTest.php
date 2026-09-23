<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPrintTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $id = 'M00001'): Member
    {
        return Member::create([
            'id' => $id,
            'name' => 'أحمد <تجربة>',
            'phone' => '0591234567',
            'gender' => 'ذكر',
            'whatsapp' => '00972591234567',
        ]);
    }

    public function test_print_form_requires_a_session(): void
    {
        $this->member();
        $this->get('/members/M00001/print')->assertUnauthorized();
    }

    public function test_staff_can_print_member_profile_subscription_and_membership_payments(): void
    {
        $this->member();
        Subscription::create([
            'id' => 'S00001', 'member_id' => 'M00001', 'plan_name' => 'اشتراك شهري',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
            'amount' => 200, 'paid' => 100, 'remaining' => 100, 'status' => 'فعال',
        ]);
        Payment::create([
            'id' => 'PAY001', 'member_id' => 'M00001', 'member_name' => 'أحمد <تجربة>',
            'subscription_id' => 'S00001', 'date' => '2026-09-03 11:30:00',
            'amount' => 100, 'method' => 'تحويل', 'transfer_from_account' => 'حساب أحمد',
            'note' => 'دفعة أولى',
        ]);
        $sale = Sale::create([
            'date' => '2026-09-04 12:00:00', 'products' => 'منتج تجريبي',
            'total' => 25, 'method' => 'نقدي',
        ]);
        Payment::create([
            'id' => 'PAY002', 'member_id' => 'M00001', 'member_name' => 'أحمد <تجربة>',
            'sale_id' => $sale->id, 'date' => '2026-09-04 12:00:00',
            'amount' => 25, 'method' => 'نقدي', 'note' => 'شراء منتج',
        ]);

        $response = $this->withSession(['user' => ['id' => 1, 'role' => 'مدير النظام']])
            ->get('/members/M00001/print');

        $response->assertOk()
            ->assertSee('أحمد &lt;تجربة&gt;', false)
            ->assertDontSee('أحمد <تجربة>', false)
            ->assertSee('لا توجد صورة')
            ->assertSee('اشتراك شهري')
            ->assertSee('حساب أحمد')
            ->assertSee('PAY001')
            ->assertDontSee('PAY002')
            ->assertDontSee('شراء منتج');
    }

    public function test_member_can_print_only_own_form(): void
    {
        $this->member('M00001');
        $this->member('M00002');

        $session = ['user' => ['id' => 2, 'role' => 'مشترك', 'member_id' => 'M00001']];
        $this->withSession($session)->get('/members/M00001/print')->assertOk();
        $this->withSession($session)->get('/members/M00002/print')->assertForbidden();
    }

    public function test_trainer_cannot_print_financial_member_form(): void
    {
        $this->member();
        $this->withSession(['user' => ['id' => 3, 'role' => 'مدرب']])
            ->get('/members/M00001/print')->assertForbidden();
    }
}
