<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    

    protected $table = 'checkins';
    public $timestamps = false;

    protected $fillable = [
        'member_id',
        'subscription_id',
        'member_name',
        'time',
        'checkout_at',
        'source',
        'status',
        'denial_reason',
    ];

    protected $casts = [
        'checkout_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }
}
