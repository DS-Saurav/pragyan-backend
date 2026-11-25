<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'adm_number',
        'first_name',
        'last_name',
        'gender',
        'dob',
        // Bikram Sambat DOB as a plain string (e.g. "2069-01-15").
        // Deliberately NOT date-cast: a B.S. date must never be parsed,
        // sorted or formatted as a Gregorian date. No automatic
        // conversion to/from the A.D. `dob` is performed.
        'dob_bs',
        'nationality',
        'languages_spoken',
        'curriculum',
        'eal',
        'class_applied',
        'school_class_id',   // link to the school_classes table
        'academic_year',
        'roll_no',
        'status',
        'house',
        'admission_date',
        'blood_group',
        'emergency_contact',
        'hobbies',
        'transport_route',
        'photo',
        'previous_school',
        'has_allergies',
        'allergy_details',
        'address',
    ];

    /**
     * Keep school_class_id in sync with the class_applied text.
     *
     * - New student with only class_applied  -> link is filled in.
     * - class_applied changes (edit / promote) -> link follows it.
     * - school_class_id given explicitly      -> left untouched.
     */
    protected static function booted(): void
    {
        static::saving(function (Student $student) {
            if (empty($student->class_applied)) {
                return;
            }

            $needsLink = empty($student->school_class_id)
                || ($student->isDirty('class_applied') && ! $student->isDirty('school_class_id'));

            if (! $needsLink) {
                return;
            }

            $class = SchoolClass::findByLabel($student->class_applied);

            if ($class) {
                $student->school_class_id = $class->id;
            }
        });
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentModel::class, 'parent_student', 'student_id', 'parent_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
