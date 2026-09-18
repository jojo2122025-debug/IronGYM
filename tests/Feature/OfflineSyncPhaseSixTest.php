<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\SyncEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineSyncPhaseSixTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_push_queue_processes_checkin_and_deduplicates_client_event(): void
    {
        $admin = $this->makeUser('admin_sync_phase6', 'مدير النظام');
        $member = $this->makeMember('M06001', 'عضو مزامنة');

        Subscription::create([
            'id' => 'S06001',
            'member_id' => $member->id,
            'plan_name' => 'اشتراك شهر',
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'amount' => 200,
            'paid' => 200,
            'remaining' => 0,
            'status' => 'فعال',
        ]);

        $payload = [
            'events' => [
                [
                    'clientEventId' => 'evt_sync_001',
                    'action' => 'check_in',
                    'payload' => ['memberId' => $member->id],
                ],
            ],
        ];

        $first = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/sync_push_queue', $payload);

        $first
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.processed', 1)
            ->assertJsonPath('summary.failed', 0);

        $this->assertDatabaseHas('sync_events', [
            'client_event_id' => 'evt_sync_001',
            'action' => 'check_in',
            'status' => 'processed',
        ]);

        $this->assertDatabaseHas('checkins', [
            'member_id' => $member->id,
            'status' => 'allowed',
        ]);

        $second = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/sync_push_queue', $payload);

        $second
            ->assertOk()
            ->assertJsonPath('summary.processed', 0)
            ->assertJsonPath('summary.skipped', 1);

        $this->assertSame(1, SyncEvent::count());
    }

    public function test_sync_push_queue_processes_checkout_basket_event(): void
    {
        $admin = $this->makeUser('admin_sync_sale_phase6', 'مدير النظام');

        Product::create([
            'id' => 'PRD601',
            'name' => 'منتج مزامنة',
            'price' => 25,
            'stock' => 10,
            'created_at' => now(),
        ]);

        $payload = [
            'events' => [
                [
                    'clientEventId' => 'evt_sync_sale_001',
                    'action' => 'checkout_basket',
                    'payload' => [
                        'paymentMethod' => 'نقدي',
                        'memberId' => '',
                        'basket' => [
                            [
                                'product' => ['id' => 'PRD601'],
                                'qty' => 2,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->postJson('/api/sync_push_queue', $payload);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.processed', 1)
            ->assertJsonPath('summary.failed', 0);

        $this->assertDatabaseHas('sync_events', [
            'client_event_id' => 'evt_sync_sale_001',
            'action' => 'checkout_basket',
            'status' => 'processed',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => 'PRD601',
            'stock' => 8,
        ]);

        $this->assertGreaterThan(0, Sale::count());
    }

    public function test_sync_events_log_can_be_filtered_by_status_and_action(): void
    {
        $admin = $this->makeUser('admin_sync_filter_phase6', 'مدير النظام');

        SyncEvent::create([
            'client_event_id' => 'evt_filter_1',
            'action' => 'check_in',
            'payload' => ['memberId' => 'M00001'],
            'status' => 'processed',
            'created_by' => $admin->id,
            'processed_at' => now(),
        ]);

        SyncEvent::create([
            'client_event_id' => 'evt_filter_2',
            'action' => 'checkout_basket',
            'payload' => ['basket' => []],
            'status' => 'failed',
            'result_message' => 'sample failure',
            'created_by' => $admin->id,
            'processed_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->getJson('/api/sync_events_log?status=failed&action=checkout_basket');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.client_event_id', 'evt_filter_2')
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.failed', 1)
            ->assertJsonPath('stats.processed', 0);
    }

    public function test_sync_events_log_can_be_filtered_by_id(): void
    {
        $admin = $this->makeUser('admin_sync_id_phase6', 'مدير النظام');

        $eventA = SyncEvent::create([
            'client_event_id' => 'evt_id_a',
            'action' => 'check_in',
            'payload' => ['memberId' => 'M00001'],
            'status' => 'processed',
            'created_by' => $admin->id,
            'processed_at' => now(),
        ]);

        SyncEvent::create([
            'client_event_id' => 'evt_id_b',
            'action' => 'check_out',
            'payload' => ['memberId' => 'M00002'],
            'status' => 'failed',
            'created_by' => $admin->id,
            'processed_at' => now(),
        ]);

        $response = $this
            ->withSession(['user' => $this->sessionUser($admin)])
            ->getJson('/api/sync_events_log?id=' . $eventA->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.client_event_id', 'evt_id_a');
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
