<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password',
        'name',
        'role',
        'member_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Available roles in Arabic
     */
    public const ROLES = [
        'مدير النظام',
        'مدير الصالة',
        'موظف الاستقبال',
        'المحاسب',
        'المدقق المالي',
        'مدرب',
        'مشترك',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    /**
     * Check if user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->role === 'مدير النظام';
    }

    public function isGymAdmin(): bool
    {
        return $this->role === 'مدير الصالة';
    }

    /**
     * Check if user is a read-only auditor
     */
    public function isReadOnly(): bool
    {
        return $this->role === 'المدقق المالي';
    }

    /**
     * Check if user is a member
     */
    public function isMember(): bool
    {
        return $this->role === 'مشترك';
    }

    public function isTrainer(): bool
    {
        return $this->role === 'مدرب';
    }

    /**
     * Check if user can manage financial operations
     */
    public function canManageFinancials(): bool
    {
        return in_array($this->role, ['مدير النظام', 'مدير الصالة', 'المحاسب']);
    }

    /**
     * Get the user session data for storing in session.
     */
    public function toSession(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'role' => $this->role,
            'member_id' => $this->member_id,
        ];
    }
}
