<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    

    protected $table = 'activity_log';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'name',
        'role',
        'action',
        'details',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Log an activity. Fails silently if logging fails (per old API).
     */
    public static function log(string $action, string $details): void
    {
        try {
            $user = session('user');
            self::create([
                'username' => $user['username'] ?? 'guest',
                'name' => $user['name'] ?? 'زائر',
                'role' => $user['role'] ?? 'زائر',
                'action' => $action,
                'details' => $details,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // fail silently
        }
    }
}
