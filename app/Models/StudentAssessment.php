<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee-free assessment-indicator rating: a student's rating on
 * ONE indicator, for ONE examination (Stage P3I).
 *
 * - indicator_key is the STABLE identity configured in the marksheet
 *   layout editor (renames must never orphan existing ratings).
 * - rating is a machine value from config("school.assessments.scale");
 *   unset indicators simply have no row - never zero, never fabricated,
 *   never auto-assigned.
 * - Ratings are exposition-specific: each exam keeps its own rows, and
 *   they are not CDC grades, grade points or GPA inputs.
 */
class StudentAssessment extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'indicator_key',
        'rating',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
