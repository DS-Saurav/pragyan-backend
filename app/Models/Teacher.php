<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'department',
        'specialization',
        'class_teacher_of',
        'status',
        'assignments',
    ];

    protected $casts = [
        'assignments' => 'array',
    ];

    /**
     * Accessor for Full Name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
