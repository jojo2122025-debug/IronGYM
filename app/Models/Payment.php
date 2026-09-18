<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    

    protected $table = 'payments';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'member_id',
        'subscription_id',
        'sale_id',
        'receipt_number',
        'member_name',
        'date',
        'amount',
        'method',
        'transfer_from_account',
        'note',
    ];

    protected $casts = [
        'date' => 'datetime',
        'amount' => 'decimal:2',
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Generate the next payment ID in the format PAY001, PAY002, ...
     */
    public static function generateNextId(): string
    {
        $maxId = self::query()
            ->selectRaw("MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)) as max_num")
            ->value('max_num') ?: 0;

        return 'PAY' . str_pad((string) ($maxId + 1), 3, '0', STR_PAD_LEFT);
    }
}
