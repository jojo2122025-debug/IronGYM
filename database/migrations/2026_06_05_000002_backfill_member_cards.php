<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $members = DB::table('members')
            ->whereNull('membership_number')
            ->orderBy('id')
            ->get();

        foreach ($members as $member) {
            DB::table('members')
                ->where('id', $member->id)
                ->update([
                    'membership_number' => $member->id,
                    'updated_at' => $now,
                ]);

            $exists = DB::table('membership_cards')
                ->where('member_id', $member->id)
                ->where('status', 'active')
                ->exists();

            if (!$exists) {
                DB::table('membership_cards')->insert([
                    'member_id' => $member->id,
                    'code' => 'GYM-' . $member->id,
                    'type' => 'qr',
                    'status' => 'active',
                    'issued_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('membership_cards')
            ->where('code', 'like', 'GYM-%')
            ->delete();

        DB::table('members')->update([
            'membership_number' => null,
            'updated_at' => now(),
        ]);
    }
};
