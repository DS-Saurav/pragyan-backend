<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_code',
        'name',
        'start_time',
        'end_time',
        'is_break',
        'sort_order',
    ];

    protected $casts = [
        'is_break' => 'boolean',
    ];
}
