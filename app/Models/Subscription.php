<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    

    protected $table = 'subscriptions';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'member_id',
        'plan_id',
        'plan_name',
        'start_date',
        'end_date',
        'amount',
        'paid',
        'remaining',
        'status',
        'frozen_from',
        'frozen_until',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'frozen_from' => 'date',
        'frozen_until' => 'date',
        'amount' => 'decimal:2',
        'paid' => 'decimal:2',
        'remaining' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    /**
     * Generate the next subscription ID in the format S001, S002, ...
     */
    public static function generateNextId(): string
    {
        $maxId = self::query()
            ->selectRaw("MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_num")
            ->value('max_num') ?: 0;

        return 'S' . str_pad((string) ($maxId + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Update the status based on the end_date and paid amount.
     * - منتهي (expired): end_date is in the past
     * - فعال (active): remaining = 0 or paid >= amount
     * - فعال (active): default
     */
    public function refreshStatus(): void
    {
        if ($this->status === 'مجمد') {
            return; // Don't change frozen status
        }

        if (Carbon::parse($this->end_date)->isPast()) {
            $this->status = 'منتهي';
        } else {
            $this->status = 'فعال';
        }
        $this->save();
    }
}
