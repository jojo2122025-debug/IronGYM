<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncEvent extends Model
{
    
    protected $fillable = [
        'client_event_id',
        'action',
        'payload',
        'status',
        'result_message',
        'processed_at',
        'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
