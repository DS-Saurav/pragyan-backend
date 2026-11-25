<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamMark extends Model
{
    protected $fillable = [
        'exam_subject_id',
        'student_id',
        'theory_marks',
        'practical_marks',
        'is_absent',
    ];
}
