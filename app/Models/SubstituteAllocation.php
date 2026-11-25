<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubstituteAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'allocation_date',
        'day_of_week',
        'absent_teacher_id',
        'substitute_teacher_id',
        'periods_covered',
        'notes',
        'status',
    ];

    protected $casts = [
        'allocation_date' => 'date',
    ];

    public function absentTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'absent_teacher_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_teacher_id');
    }
}
