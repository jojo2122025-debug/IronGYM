<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipCard extends Model
{
    

    protected $fillable = [
        'member_id',
        'code',
        'type',
        'status',
        'issued_at',
        'revoked_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }
}
