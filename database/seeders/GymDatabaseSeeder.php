<?php

namespace Database\Seeders;

use App\Models\Checkin;
use App\Models\Member;
use App\Models\Measurement;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
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
