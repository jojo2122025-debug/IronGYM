<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingProgram extends Model
{
    
    protected $fillable = [
        'trainer_assignment_id',
        'title',
        'goal',
        'content_json',
    ];

    protected $casts = [
        'content_json' => 'array',
    ];

    public function trainerAssignment(): BelongsTo
    {
        return $this->belongsTo(TrainerAssignment::class, 'trainer_assignment_id');
    }
}
