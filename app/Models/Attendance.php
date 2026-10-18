<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    public const PRESENT = 'present';

    public const ABSENT = 'absent';

    public const LATE = 'late';

    public const HALF_DAY = 'half_day';

    public const LEAVE = 'leave';

    // Must match the enum in the attendances migration
    public const STATUSES = [
        self::PRESENT,
        self::ABSENT,
        self::LATE,
        self::HALF_DAY,
        self::LEAVE,
    ];

    protected $fillable = [
        'school_class_id',
        'student_id',
        'date',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
