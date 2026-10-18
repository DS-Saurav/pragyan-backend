<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'max_marks',
        'passing_marks',
        'assigned_classes',
    ];

    protected $casts = [
        'assigned_classes' => 'array',
    ];
}
