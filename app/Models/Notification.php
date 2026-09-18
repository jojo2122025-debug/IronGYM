<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'template_id',
        'channel',
        'type',
        'title',
        'body',
        'status',
        'scheduled_at',
        'sent_at',
        'provider_response',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
