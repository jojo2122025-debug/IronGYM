<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        return ['id' => 1, 'username' => 'admin', 'name' => 'المدير', 'role' => 'مدير النظام'];
    }

    public function test_import_links_legacy_id_to_generated_member_and_is_repeatable(): void
    {
        $payload = [
            'members' => [[
                'id' => 'LEGACY-123456789', 'name' => 'أحمد محمد', 'phone' => '0591234567',
                'whatsapp' => '', 'gender' => 'ذكر',
            ]],
            'subscriptions' => [[
                'member_id' => 'LEGACY-123456789', 'plan_name' => 'شهري',
                'amount' => 100, 'paid' => 50, 'remaining' => 50,
                'start_date' => '01-09-2026', 'end_date' => '30-09-2026', 'status' => 'فعال',
            ]],
            'payments' => [[
                'member_id' => 'LEGACY-123456789', 'amount' => 50, 'method' => 'تحويل',
                'transfer_from_account' => 'حساب أحمد', 'date' => '01-09-2026 10:30', 'note' => 'اشتراك',
            ]],
        ];

        $first = $this->withSession(['user' => $this->admin()])->postJson('/api/import_data', $payload);
        $first->assertOk()->assertJsonPath('imported_members_count', 1)
            ->assertJsonPath('imported_subs_count', 1)
            ->assertJsonPath('imported_pays_count', 1);

        $member = Member::query()->firstOrFail();
        $this->assertSame('M00001', $member->id);
        $this->assertSame($member->id, Subscription::query()->firstOrFail()->member_id);
        $payment = Payment::query()->firstOrFail();
        $this->assertSame($member->id, $payment->member_id);
        $this->assertSame('حساب أحمد', $payment->transfer_from_account);

        $second = $this->withSession(['user' => $this->admin()])->postJson('/api/import_data', $payload);
        $second->assertOk()->assertJsonPath('updated_members_count', 1)
            ->assertJsonPath('skipped_subs_count', 1)
            ->assertJsonPath('skipped_pays_count', 1);
        $this->assertSame(1, Member::count());
        $this->assertSame(1, Subscription::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_import_counts_invalid_and_unmatched_rows(): void
    {
        $response = $this->withSession(['user' => $this->admin()])->postJson('/api/import_data', [
            'members' => [['name' => '', 'phone' => '']],
            'subscriptions' => [[
                'member_id' => 'M99999', 'plan_name' => 'شهري', 'amount' => 100,
                'paid' => 0, 'remaining' => 100, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
            ]],
            'payments' => [[
                'member_name' => 'غير موجود', 'amount' => 50, 'method' => 'نقدي',
                'date' => '2026-09-01', 'note' => '',
            ]],
        ]);

        $response->assertOk()->assertJsonPath('skipped_members_count', 1)
            ->assertJsonPath('skipped_subs_count', 1)
            ->assertJsonPath('skipped_pays_count', 1);
        $this->assertCount(3, $response->json('skip_details'));
        $this->assertSame(0, Member::count());
    }

    public function test_import_links_rows_by_unique_member_name_without_source_id(): void
    {
        $response = $this->withSession(['user' => $this->admin()])->postJson('/api/import_data', [
            'members' => [['name' => 'سارة أحمد', 'phone' => '0591000000', 'gender' => 'أنثى']],
            'subscriptions' => [[
                'member_name' => 'سارة أحمد', 'plan_name' => 'شهري', 'amount' => 100,
                'paid' => 0, 'remaining' => 100, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
            ]],
            'payments' => [[
                'member_name' => 'سارة أحمد', 'amount' => 25, 'method' => 'نقدي',
                'date' => '2026-09-01', 'note' => 'دفعة',
            ]],
        ]);

        $response->assertOk()->assertJsonPath('imported_subs_count', 1)
            ->assertJsonPath('imported_pays_count', 1);
        $memberId = Member::query()->firstOrFail()->id;
        $this->assertSame($memberId, Subscription::query()->firstOrFail()->member_id);
        $this->assertSame($memberId, Payment::query()->firstOrFail()->member_id);
    }
}
