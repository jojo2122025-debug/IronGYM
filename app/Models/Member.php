<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $table = 'members';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'phone',
        'gender',
        'whatsapp',
        'image_path',
        'membership_number',
        'birth_date',
        'notes',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'member_id', 'id');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class, 'member_id', 'id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class, 'member_id', 'id');
    }

    public function membershipCards(): HasMany
    {
        return $this->hasMany(MembershipCard::class, 'member_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'member_id', 'id');
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class, 'member_id', 'id')
            ->where('status', 'فعال')
            ->latestOfMany('start_date');
    }

    /**
     * Generate the next member ID in the format M00001, M00002, ...
     */
    public static function generateNextId(): string
    {
        $maxId = self::query()
            ->selectRaw("MAX(CAST(SUBSTRING(id, 2) AS UNSIGNED)) as max_num")
            ->value('max_num') ?: 0;

        return 'M' . str_pad((string) ($maxId + 1), 5, '0', STR_PAD_LEFT);
    }
}
