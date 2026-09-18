<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'members',
            'plans',
            'subscriptions',
            'payments',
            'products',
            'sales',
            'sale_items',
            'checkins',
            'measurements',
            'membership_cards',
            'trainers',
            'trainer_assignments',
            'training_programs',
            'nutrition_programs',
            'notifications',
            'notification_templates',
            'sync_events',
            'activity_log',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                if (!Schema::hasColumn($blueprint->getTable(), 'gym_id')) {
                    $blueprint->unsignedBigInteger('gym_id')->nullable()->index();
                }
                if (!Schema::hasColumn($blueprint->getTable(), 'branch_id')) {
                    $blueprint->unsignedBigInteger('branch_id')->nullable()->index();
                }
            });
        }

        $defaultGymId = null;
        $defaultBranchId = null;

        if (Schema::hasTable('gyms') && Schema::hasTable('branches')) {
            $defaultGymId = DB::table('gyms')->orderBy('id')->value('id');
            $defaultBranchId = DB::table('branches')->orderBy('id')->value('id');
        }

        if ($defaultGymId && $defaultBranchId) {
            foreach ($tables as $table) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'gym_id') || !Schema::hasColumn($table, 'branch_id')) {
                    continue;
                }

                DB::table($table)
                    ->whereNull('gym_id')
                    ->update([
                        'gym_id' => $defaultGymId,
                        'branch_id' => $defaultBranchId,
                    ]);
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'members',
            'plans',
            'subscriptions',
            'payments',
            'products',
            'sales',
            'sale_items',
            'checkins',
            'measurements',
            'membership_cards',
            'trainers',
            'trainer_assignments',
            'training_programs',
            'nutrition_programs',
            'notifications',
            'notification_templates',
            'sync_events',
            'activity_log',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                foreach (['branch_id', 'gym_id'] as $column) {
                    if (Schema::hasColumn($blueprint->getTable(), $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
