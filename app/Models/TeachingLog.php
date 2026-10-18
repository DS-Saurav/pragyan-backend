<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingLog extends Model
{
    public const COVERAGES = ['completed', 'partial', 'not_covered'];

    protected $fillable = [
        'teacher_id', 'school_class_id', 'subject_id', 'lesson_plan_id',
        'log_date', 'period', 'topic_covered', 'coverage', 'homework_given',
        'students_present', 'students_absent', 'remarks',
    ];

    protected $casts = [
        'log_date' => 'date',
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

    public function lessonPlan(): BelongsTo
    {
        return $this->belongsTo(LessonPlan::class);
    }
}
