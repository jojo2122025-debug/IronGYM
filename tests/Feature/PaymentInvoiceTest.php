<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $id = 'M00001'): Member
    {
        return Member::create([
            'id' => $id, 'name' => 'أحمد <تجربة>', 'phone' => '0591234567', 'gender' => 'ذكر',
        ]);
    }

    private function payment(string $id = 'PAY001', ?string $memberId = 'M00001'): Payment
    {
        return Payment::create([
            'id' => $id, 'member_id' => $memberId, 'member_name' => 'أحمد <تجربة>',
            'date' => '2026-09-23 12:35:00', 'amount' => 120,
            'method' => 'تحويل', 'transfer_from_account' => 'حساب أحمد', 'note' => 'دفعة أولى',
        ]);
    }

    public function test_invoice_is_a5_and_escapes_payment_data(): void
    {
        $this->member();
        $this->payment();

        $this->withSession(['user' => ['id' => 1, 'role' => 'مدير النظام']])
            ->get('/payments/PAY001/invoice')
            ->assertOk()
            ->assertSee('size:A5 portrait', false)
            ->assertSee('PAY001')
            ->assertSee('120.00 ₪')
            ->assertSee('حساب أحمد')
            ->assertSee('أحمد &lt;تجربة&gt;', false)
            ->assertDontSee('أحمد <تجربة>', false)
            ->assertSee('23-09-2026 12:35');
    }

    public function test_invoice_access_is_limited_to_staff_and_payment_owner(): void
    {
        $this->member();
        $this->member('M00002');
        $this->payment();

        $this->get('/payments/PAY001/invoice')->assertUnauthorized();
        $this->withSession(['user' => ['id' => 2, 'role' => 'مشترك', 'member_id' => 'M00002']])
            ->get('/payments/PAY001/invoice')->assertForbidden();
        $this->withSession(['user' => ['id' => 3, 'role' => 'مدرب']])
            ->get('/payments/PAY001/invoice')->assertForbidden();
        $this->withSession(['user' => ['id' => 4, 'role' => 'مشترك', 'member_id' => 'M00001']])
            ->get('/payments/PAY001/invoice')->assertOk();
        $this->withSession(['user' => ['id' => 1, 'role' => 'مدير النظام']])
            ->get('/payments/UNKNOWN/invoice')->assertNotFound();
    }

    public function test_paid_subscription_returns_invoice_payment_id(): void
    {
        $this->member();
        Plan::create(['id' => 'P001', 'name' => 'اشتراك شهري', 'price' => 200, 'days' => 30]);

        $response = $this->withSession(['user' => ['id' => 1, 'role' => 'مدير النظام']])
            ->post('/api/add_subscription', [
                'memberId' => 'M00001', 'planId' => 'P001',
                'startDate' => '2026-09-23', 'paid' => 100,
            ])
            ->assertOk()->assertJsonPath('success', true);

        $paymentId = $response->json('paymentId');
        $this->assertNotEmpty($paymentId);
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'amount' => 100]);
        $this->withSession(['user' => ['id' => 1, 'role' => 'مدير النظام']])
            ->get('/payments/' . $paymentId . '/invoice')->assertOk()->assertSee('اشتراك شهري');
    }

    public function test_unpaid_subscription_does_not_claim_an_invoice(): void
    {
        $this->member();
        Plan::create(['id' => 'P001', 'name' => 'اشتراك شهري', 'price' => 200, 'days' => 30]);

        $this->withSession(['user' => ['id' => 1, 'role' => 'مدير النظام']])
            ->post('/api/add_subscription', [
                'memberId' => 'M00001', 'planId' => 'P001',
                'startDate' => '2026-09-23', 'paid' => 0,
            ])->assertOk()->assertJsonPath('paymentId', null);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_manual_payment_returns_a_printable_invoice_id(): void
    {
        $this->member();

        $response = $this->withSession(['user' => ['id' => 1, 'role' => 'مدير النظام']])
            ->post('/api/add_payment', [
                'memberId' => 'M00001', 'amount' => 75,
                'method' => 'نقدي', 'note' => 'دفعة عضوية',
            ])->assertOk()->assertJsonPath('success', true);

        $this->get('/payments/' . $response->json('id') . '/invoice')
            ->assertOk()->assertSee('75.00 ₪')->assertSee('دفعة عضوية');
    }

    public function test_product_sale_returns_invoice_with_item_details(): void
    {
        Product::create(['id' => 'PRD001', 'name' => 'مكمل بروتين', 'price' => 40, 'stock' => 5]);

        $response = $this->withSession(['user' => ['id' => 1, 'role' => 'مدير النظام']])
            ->post('/api/checkout_basket', [
                'basket' => [['product' => ['id' => 'PRD001'], 'qty' => 2]],
                'paymentMethod' => 'نقدي',
            ])->assertOk()->assertJsonPath('success', true);

        $paymentId = $response->json('paymentId');
        $this->assertNotEmpty($paymentId);
        $this->withSession(['user' => ['id' => 1, 'role' => 'مدير النظام']])
            ->get('/payments/' . $paymentId . '/invoice')
            ->assertOk()->assertSee('مكمل بروتين')->assertSee('80.00 ₪');
    }
}
