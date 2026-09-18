<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTransferFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_payment_can_store_source_account_name(): void
    {
        $member = Member::create([
            'id' => Member::generateNextId(),
            'name' => 'سالم أحمد',
            'phone' => '0599999999',
        ]);

        session()->put('user', [
            'id' => 1,
            'name' => 'مدير',
            'role' => 'المحاسب',
            'username' => 'accountant',
        ]);

        $response = $this->postJson('/api/gym/add_payment', [
            'memberId' => $member->id,
            'amount' => 120,
            'method' => 'تحويل',
            'transferFromAccount' => 'حساب البنك الأهلي',
            'note' => 'دفعة تحويل',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $payment = Payment::query()->latest('created_at')->first();
        $this->assertNotNull($payment);
        $this->assertSame('حساب البنك الأهلي', $payment->transfer_from_account);
    }
}
