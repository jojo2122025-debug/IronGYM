<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    

    protected $table = 'products';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'price',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Generate the next product ID in the format PRD001, PRD002, ...
     */
    public static function generateNextId(): string
    {
        $maxId = self::query()
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as max_num")
            ->value('max_num') ?: 0;

        return 'PRD' . str_pad((string) ($maxId + 1), 3, '0', STR_PAD_LEFT);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'product_id', 'id');
    }
}
