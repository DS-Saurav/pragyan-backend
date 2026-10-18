<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPlan extends Model
{
    public const STATUSES = ['planned', 'in_progress', 'completed', 'postponed'];

    protected $fillable = [
        'teacher_id', 'school_class_id', 'subject_id',
        'lesson_date', 'period', 'duration_minutes',
        'unit', 'topic', 'objectives', 'teaching_method', 'resources',
        'homework', 'assessment', 'status', 'reflection',
    ];

    protected $casts = [
        'lesson_date' => 'date',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
