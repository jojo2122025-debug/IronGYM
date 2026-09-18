<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Checkin;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Measurement;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class GymDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Use a transaction for consistency
        DB::transaction(function () {
            $this->seedMembers();
            $this->seedPlans();
            $this->seedSubscriptions();
            $this->seedPayments();
            $this->seedProducts();
            $this->seedSales();
            $this->seedCheckins();
            $this->seedMeasurements();
            $this->seedUsers();
        });
    }

    private function seedMembers(): void
    {
        $now = now();
        $members = [
            ['id' => 'M00001', 'name' => 'TEST_Ahmed Ali', 'phone' => '0500000001', 'gender' => 'ذكر', 'whatsapp' => '0500000001'],
            ['id' => 'M00002', 'name' => 'TEST_NoSub User', 'phone' => '0500000099', 'gender' => 'ذكر', 'whatsapp' => '0500000099'],
            ['id' => 'M00003', 'name' => 'TEST_UI Member', 'phone' => '0599999000', 'gender' => 'أنثى', 'whatsapp' => '0599999000'],
            ['id' => 'M00004', 'name' => 'محمد خالد سالم الرباعي', 'phone' => '0599466856', 'gender' => 'ذكر', 'whatsapp' => '0599466856'],
            ['id' => 'M00005', 'name' => 'محمود', 'phone' => '0599555652', 'gender' => 'ذكر', 'whatsapp' => '0599555652'],
        ];

        foreach ($members as $m) {
            Member::firstOrCreate(
                ['id' => $m['id']],
                array_merge($m, ['created_at' => $now])
            );
        }
    }

    private function seedPlans(): void
    {
        $now = now();
        $plans = [
            ['id' => 'P001', 'name' => 'اشتراك شهر', 'description' => 'اشتراك شهري', 'price' => 200.00, 'days' => 30],
            ['id' => 'P002', 'name' => 'اشتراك شهرين', 'description' => 'اشتراك شهرين', 'price' => 380.00, 'days' => 60],
            ['id' => 'P003', 'name' => 'اشتراك ٣ أشهر', 'description' => 'اشتراك ربع سنوي', 'price' => 540.00, 'days' => 90],
            ['id' => 'P004', 'name' => 'VIP سنوي', 'description' => 'اشتراك VIP سنوي', 'price' => 1800.00, 'days' => 365],
        ];

        foreach ($plans as $p) {
            Plan::firstOrCreate(
                ['id' => $p['id']],
                array_merge($p, ['created_at' => $now])
            );
        }
    }

    private function seedGymsAndBranches(): void
    {
        $defaultSettings = ['currency' => 'ILS', 'locale' => 'ar'];

        $gymSeeds = [
            ['name' => 'الصالة الرئيسية', 'code' => 'main-gym', 'branch_name' => 'الفرع الرئيسي', 'branch_code' => 'main-branch'],
            ['name' => 'صالة السلام', 'code' => 'salem-gym', 'branch_name' => 'الفرع الرئيسي', 'branch_code' => 'salem-branch'],
            ['name' => 'صالة الرياضة', 'code' => 'riyadah-gym', 'branch_name' => 'الفرع الرئيسي', 'branch_code' => 'riyadah-branch'],
            ['name' => 'صالة النخبة', 'code' => 'elite-gym', 'branch_name' => 'الفرع الرئيسي', 'branch_code' => 'elite-branch'],
        ];

        foreach ($gymSeeds as $seed) {
            $gym = Gym::firstOrCreate(
                ['code' => $seed['code']],
                ['name' => $seed['name'], 'status' => 'active', 'settings' => $defaultSettings]
            );

            Branch::firstOrCreate(
                ['gym_id' => $gym->id, 'code' => $seed['branch_code']],
                ['name' => $seed['branch_name'], 'status' => 'active', 'is_default' => true]
            );
        }
    }

    private function seedGymMembersAndSubscriptions(): void
    {
        $targets = [
            'main-gym' => 1000,
            'salem-gym' => 1000,
            'riyadah-gym' => 1000,
            'elite-gym' => 1000,
        ];

        $now = now();

        $nextMemberIndex = Member::query()
            ->selectRaw('MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_num')
            ->value('max_num') ?: 0;
        $nextMemberIndex++;

        $nextSubscriptionIndex = Subscription::query()
            ->selectRaw('MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_num')
            ->value('max_num') ?: 0;
        $nextSubscriptionIndex++;

        $phoneCounter = 1000;

        foreach ($targets as $gymCode => $targetCount) {
            $gym = Gym::where('code', $gymCode)->first();
            if (!$gym) {
                continue;
            }

            $branch = $gym->branches()->where('is_default', true)->first();
            if (!$branch) {
                $branch = $gym->branches()->first();
            }
            if (!$branch) {
                continue;
            }

            $existingCount = Member::where('gym_id', $gym->id)->count();
            $neededMembers = max(0, $targetCount - $existingCount);
            $memberRows = [];

            for ($index = 0; $index < $neededMembers; $index++) {
                $memberRows[] = [
                    'id' => 'M' . str_pad((string) $nextMemberIndex, 5, '0', STR_PAD_LEFT),
                    'name' => "عضو تجريبي {$gym->name} " . ($existingCount + $index + 1),
                    'phone' => '050' . str_pad((string) $phoneCounter, 7, '0', STR_PAD_LEFT),
                    'whatsapp' => '050' . str_pad((string) $phoneCounter, 7, '0', STR_PAD_LEFT),
                    'gender' => $index % 2 === 0 ? 'ذكر' : 'أنثى',
                    'gym_id' => $gym->id,
                    'branch_id' => $branch->id,
                    'created_at' => $now,
                ];

                $nextMemberIndex++;
                $phoneCounter++;
            }

            if (!empty($memberRows)) {
                DB::table('members')->insert($memberRows);
            }

            $this->seedGymSubscriptions($gym, $branch, 4, $nextSubscriptionIndex, $now);
        }
    }

    private function seedGymSubscriptions(Gym $gym, Branch $branch, int $targetSubscriptions, int &$nextSubscriptionIndex, $now): void
    {
        $existingSubscriptions = Subscription::where('gym_id', $gym->id)->count();
        $neededSubscriptions = max(0, $targetSubscriptions - $existingSubscriptions);
        if ($neededSubscriptions === 0) {
            return;
        }

        $memberIds = Member::where('gym_id', $gym->id)
            ->orderBy('id')
            ->pluck('id')
            ->take($neededSubscriptions);

        if ($memberIds->count() < $neededSubscriptions) {
            return;
        }

        $plans = [
            ['plan_name' => 'اشتراك شهر', 'amount' => 200.00, 'duration' => 30, 'status' => 'فعال'],
            ['plan_name' => 'اشتراك شهرين', 'amount' => 380.00, 'duration' => 60, 'status' => 'فعال'],
            ['plan_name' => 'اشتراك ٣ أشهر', 'amount' => 540.00, 'duration' => 90, 'status' => 'مجمد'],
            ['plan_name' => 'VIP سنوي', 'amount' => 1800.00, 'duration' => 365, 'status' => 'فعال'],
        ];

        $subscriptionRows = [];
        foreach ($memberIds as $index => $memberId) {
            $plan = $plans[$index % count($plans)];
            $startDate = $now->copy()->subDays(5 + $index)->toDateString();
            $endDate = $now->copy()->addDays($plan['duration'])->toDateString();

            $subscriptionRows[] = [
                'id' => 'S' . str_pad((string) $nextSubscriptionIndex, 3, '0', STR_PAD_LEFT),
                'member_id' => $memberId,
                'plan_name' => $plan['plan_name'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'amount' => $plan['amount'],
                'paid' => $plan['amount'],
                'remaining' => 0.00,
                'status' => $plan['status'],
                'gym_id' => $gym->id,
                'branch_id' => $branch->id,
                'created_at' => $now,
            ];

            $nextSubscriptionIndex++;
        }

        if (!empty($subscriptionRows)) {
            DB::table('subscriptions')->insert($subscriptionRows);
        }
    }

    private function assignDefaultGymBranchToLegacyRecords(): void
    {
        $mainGym = Gym::where('code', 'main-gym')->first();
        if (!$mainGym) {
            return;
        }

        $defaultBranch = Branch::where('gym_id', $mainGym->id)
            ->where('code', 'main-branch')
            ->first();

        if (!$defaultBranch) {
            return;
        }

        $tables = [
            'members',
            'plans',
            'subscriptions',
            'payments',
            'products',
            'sales',
            'checkins',
            'measurements',
            'users',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'gym_id') || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            DB::table($table)
                ->whereNull('gym_id')
                ->update([
                    'gym_id' => $mainGym->id,
                    'branch_id' => $defaultBranch->id,
                ]);
        }
    }

    private function seedSubscriptions(): void
    {
        $now = now();
        $subs = [
            ['id' => 'S001', 'member_id' => 'M00005', 'plan_name' => 'اشتراك شهرين', 'start_date' => '2026-06-01', 'end_date' => '2026-07-31', 'amount' => 380.00, 'paid' => 120.00, 'remaining' => 260.00, 'status' => 'مجمد'],
            ['id' => 'S002', 'member_id' => 'M00003', 'plan_name' => 'اشتراك شهر', 'start_date' => '2026-05-07', 'end_date' => '2026-06-06', 'amount' => 200.00, 'paid' => 100.00, 'remaining' => 100.00, 'status' => 'فعال'],
            ['id' => 'S003', 'member_id' => 'M00001', 'plan_name' => 'اشتراك شهر', 'start_date' => '2026-05-07', 'end_date' => '2026-06-06', 'amount' => 200.00, 'paid' => 80.00, 'remaining' => 120.00, 'status' => 'فعال'],
        ];

        foreach ($subs as $s) {
            Subscription::firstOrCreate(
                ['id' => $s['id']],
                array_merge($s, ['created_at' => $now])
            );
        }
    }

    private function seedPayments(): void
    {
        $payments = [
            ['id' => 'PAY001', 'member_name' => 'محمود', 'date' => '2026-06-01 20:10:00', 'amount' => 120.00, 'method' => 'نقدي', 'note' => 'دفعة اشتراك'],
            ['id' => 'PAY002', 'member_name' => 'TEST_UI Member', 'date' => '2026-05-07 19:59:00', 'amount' => 100.00, 'method' => 'نقدي', 'note' => 'دفعة اشتراك'],
            ['id' => 'PAY003', 'member_name' => 'TEST_Ahmed Ali', 'date' => '2026-05-07 19:59:00', 'amount' => 30.00, 'method' => 'نقدي', 'note' => '—'],
            ['id' => 'PAY004', 'member_name' => 'TEST_Ahmed Ali', 'date' => '2026-05-07 19:59:00', 'amount' => 50.00, 'method' => 'نقدي', 'note' => 'دفعة اشتراك'],
        ];

        foreach ($payments as $p) {
            Payment::firstOrCreate(
                ['id' => $p['id']],
                $p
            );
        }
    }

    private function seedProducts(): void
    {
        $now = now();
        $products = [
            ['id' => 'PRD001', 'name' => 'TEST_Protein', 'price' => 100.00, 'stock' => 7],
            ['id' => 'PRD002', 'name' => 'TEST_UI_Prod', 'price' => 50.00, 'stock' => 4],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(
                ['id' => $p['id']],
                array_merge($p, ['created_at' => $now])
            );
        }
    }

    private function seedSales(): void
    {
        $sales = [
            ['date' => '2026-06-01 20:20:00', 'products' => 'TEST_Protein ×1، TEST_UI_Prod ×1', 'total' => 150.00, 'method' => 'تحويل'],
            ['date' => '2026-05-07 19:59:00', 'products' => 'TEST_Protein ×2', 'total' => 200.00, 'method' => 'نقدي'],
        ];

        foreach ($sales as $s) {
            Sale::firstOrCreate(
                ['date' => $s['date'], 'products' => $s['products'], 'total' => $s['total'], 'method' => $s['method']],
                $s
            );
        }
    }

    private function seedCheckins(): void
    {
        $checkins = [
            ['member_id' => 'M00001', 'member_name' => 'TEST_Ahmed Ali', 'time' => '08:30 ص', 'created_at' => '2026-06-02 08:30:00'],
            ['member_id' => 'M00003', 'member_name' => 'TEST_UI Member', 'time' => '10:00 ص', 'created_at' => '2026-06-02 10:00:00'],
            ['member_id' => 'M00004', 'member_name' => 'محمد خالد سالم الرباعي', 'time' => '06:00 م', 'created_at' => '2026-06-02 18:00:00'],
        ];

        foreach ($checkins as $c) {
            Checkin::firstOrCreate(
                ['member_id' => $c['member_id'], 'created_at' => $c['created_at']],
                $c
            );
        }
    }

    private function seedMeasurements(): void
    {
        $measurements = [
            ['member_id' => 'M00001', 'weight' => 75.50, 'height' => 175.00, 'fat_percentage' => 18.50, 'muscle_mass' => 35.20],
            ['member_id' => 'M00003', 'weight' => 62.00, 'height' => 165.00, 'fat_percentage' => 22.00, 'muscle_mass' => 28.50],
            ['member_id' => 'M00004', 'weight' => 85.00, 'height' => 180.00, 'fat_percentage' => 16.00, 'muscle_mass' => 40.00],
        ];

        foreach ($measurements as $m) {
            Measurement::firstOrCreate(
                ['member_id' => $m['member_id']],
                $m
            );
        }
    }

    private function seedUsers(): void
    {
        $now = now();
        $adminPassword = env('INITIAL_ADMIN_PASSWORD');

        if (!is_string($adminPassword) || $adminPassword === '') {
            throw new RuntimeException('INITIAL_ADMIN_PASSWORD must be set before seeding the application.');
        }

        $users = [
            ['username' => 'admin', 'password' => $adminPassword, 'name' => 'مدير النظام', 'role' => 'مدير النظام', 'member_id' => null],
            ['username' => 'gymadmin', 'password' => str()->password(32), 'name' => 'مدير الصالة', 'role' => 'مدير الصالة', 'member_id' => null],
            ['username' => 'receptionist', 'password' => str()->password(32), 'name' => 'موظف الاستقبال', 'role' => 'موظف الاستقبال', 'member_id' => null],
            ['username' => 'accountant', 'password' => str()->password(32), 'name' => 'المحاسب', 'role' => 'المحاسب', 'member_id' => null],
            ['username' => 'auditor', 'password' => str()->password(32), 'name' => 'المدقق المالي', 'role' => 'المدقق المالي', 'member_id' => null],
            ['username' => 'M00001', 'password' => str()->password(32), 'name' => 'TEST_Ahmed Ali', 'role' => 'مشترك', 'member_id' => 'M00001'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['username' => $u['username']],
                [
                    'password' => Hash::make($u['password']),
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'member_id' => $u['member_id'],
                    'created_at' => $now,
                ]
            );
        }
    }
}
