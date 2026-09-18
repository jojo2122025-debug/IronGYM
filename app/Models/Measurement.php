<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Measurement extends Model
{
    

    protected $table = 'measurements';
    public $timestamps = false;

    protected $fillable = [
        'member_id',
        'weight',
        'height',
        'fat_percentage',
        'muscle_mass',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'fat_percentage' => 'decimal:2',
        'muscle_mass' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }
}
