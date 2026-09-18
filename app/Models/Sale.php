<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    

    protected $table = 'sales';
    public $timestamps = false;

    protected $fillable = [
        'date',
        'products',
        'total',
        'method',
    ];

    protected $casts = [
        'date' => 'datetime',
        'total' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
