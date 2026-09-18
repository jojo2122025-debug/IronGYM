<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Branch;

class Gym extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
