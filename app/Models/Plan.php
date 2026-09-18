<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $table = 'plans';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'price',
        'days',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'days' => 'integer',
        'created_at' => 'datetime',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id', 'id');
    }

    /**
     * Generate the next plan ID in the format P001, P002, ...
     */
    public static function generateNextId(): string
    {
        $maxId = self::query()
            ->selectRaw("MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_num")
            ->value('max_num') ?: 0;

        return 'P' . str_pad((string) ($maxId + 1), 3, '0', STR_PAD_LEFT);
    }
}
