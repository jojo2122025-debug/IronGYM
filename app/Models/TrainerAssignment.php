<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainerAssignment extends Model
{
    
    protected $fillable = [
        'trainer_id',
        'member_id',
        'start_date',
        'end_date',
        'status',
        'note',
    ];

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class, 'trainer_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    public function trainingPrograms(): HasMany
    {
        return $this->hasMany(TrainingProgram::class, 'trainer_assignment_id');
    }

    public function nutritionPrograms(): HasMany
    {
        return $this->hasMany(NutritionProgram::class, 'trainer_assignment_id');
    }
}
