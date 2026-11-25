<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSubject extends Model
{
    protected $fillable = [
        'exam_id', 'school_class_id', 'subject_id', 'exam_date',
        'theory_full', 'theory_pass', 'practical_full', 'practical_pass', 'marks_status', 'start_time', 'end_time',
        'credit_hours'
    ];

    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'theory_full' => 'float',
        'theory_pass' => 'float',
        'practical_full' => 'float',
        'practical_pass' => 'float',
        'credit_hours' => 'float',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(ExamMark::class);
    }

    public function getFullMarksAttribute(): float
    {
        return $this->theory_full + $this->practical_full;
    }
}
