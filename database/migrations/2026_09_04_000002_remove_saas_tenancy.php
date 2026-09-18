<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users', 'members', 'plans', 'subscriptions', 'payments', 'products',
            'sales', 'sale_items', 'checkins', 'measurements', 'membership_cards',
            'trainers', 'trainer_assignments', 'training_programs', 'nutrition_programs',
            'notifications', 'notification_templates', 'sync_events', 'activity_log',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                foreach (['gym_id', 'branch_id'] as $column) {
                    if (Schema::hasColumn($blueprint->getTable(), $column)) {
                        $blueprint->dropIndex([$column]);
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('branches');
        Schema::dropIfExists('gyms');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // SaaS tenancy is intentionally not recreated.
    }
};
