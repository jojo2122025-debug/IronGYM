<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gyms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 50)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gym_id')->index();
            $table->string('name', 120);
            $table->string('code', 50);
            $table->string('status', 20)->default('active')->index();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();

            $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
            $table->unique(['gym_id', 'code']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'gym_id')) {
                $table->unsignedBigInteger('gym_id')->nullable()->index();
            }
            if (!Schema::hasColumn('users', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->index();
            }
        });

        $now = now();
        DB::table('gyms')->insert([
            'name' => 'الصالة الرئيسية',
            'code' => 'main-gym',
            'status' => 'active',
            'settings' => json_encode(['currency' => 'ILS', 'locale' => 'ar']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $defaultGymId = DB::table('gyms')->where('code', 'main-gym')->value('id');

        DB::table('branches')->insert([
            'gym_id' => $defaultGymId,
            'name' => 'الفرع الرئيسي',
            'code' => 'main-branch',
            'status' => 'active',
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $defaultBranchId = DB::table('branches')->where('gym_id', $defaultGymId)->where('code', 'main-branch')->value('id');

        DB::table('users')
            ->whereNull('gym_id')
            ->update([
                'gym_id' => $defaultGymId,
                'branch_id' => $defaultBranchId,
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['branch_id', 'gym_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('branches');
        Schema::dropIfExists('gyms');
    }
};
