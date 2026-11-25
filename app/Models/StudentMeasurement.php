<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One authoritative height/weight record per student per academic year
 * (Stage P3G). Height in centimetres, weight in kilograms - either may
 * be unset (null), which must never be read as zero. Older academic
 * years keep their own rows: the students table is updated in place on
 * promotion, so only this table preserves measurement history.
 */
class StudentMeasurement extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year',
        'height_cm',
        'weight_kg',
        'measured_on',
    ];

    protected $casts = [
        'height_cm' => 'float',
        'weight_kg' => 'float',
        'measured_on' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
