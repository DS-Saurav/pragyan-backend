<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    public const TYPES = [
        'unit_test' => 'Unit Test',
        'first_terminal' => 'First Terminal',
        'second_terminal' => 'Second Terminal',
        'third_terminal' => 'Third Terminal / Final',
        'half_yearly' => 'Half Yearly',
        'quarterly' => 'Quarterly',
        'pre_board' => 'Pre-Board',
        'other' => 'Other',
    ];

    public const STATUSES = ['scheduled', 'ongoing', 'completed', 'published'];

    protected $fillable = ['name', 'type', 'academic_year', 'start_date', 'end_date', 'status', 'published_at'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'published_at' => 'datetime',
    ];

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }
}
