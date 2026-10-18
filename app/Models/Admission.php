<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Admission extends Model
{
    use HasFactory;

    protected $fillable = [
        'reg_number',
        'first_name',
        'last_name',
        'gender',
        'dob',
        'class_applied',
        'academic_year',
        'address',          
        'parent_name',
        'relation',         
        'mobile',
        'email',           
        'status',
        'rejection_reason',
    ];
}