<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

trait TenantScoped
{
    /**
     * @mixin \Illuminate\Database\Eloquent\Model
     */
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope('tenant_branch_scope', function (Builder $builder) {
            $user = session('user');
            $branchId = $user['branch_id'] ?? null;
            if (!$branchId) {
                return;
            }

            $table = $builder->getModel()->getTable();
            if (!Schema::hasColumn($table, 'branch_id')) {
                return;
            }

            $builder->where("{$table}.branch_id", $branchId);
        });

        static::creating(function (Model $model) {
            $user = session('user');
            if (!$user) {
                return;
            }

            $table = $model->getTable();
            if (Schema::hasColumn($table, 'gym_id') && empty($model->getAttribute('gym_id'))) {
                $model->setAttribute('gym_id', $user['gym_id'] ?? null);
            }
            if (Schema::hasColumn($table, 'branch_id') && empty($model->getAttribute('branch_id'))) {
                $model->setAttribute('branch_id', $user['branch_id'] ?? null);
            }
        });
    }
}
