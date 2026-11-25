<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    public const TYPES = ['earning' => 'Earning (allowance)', 'deduction' => 'Deduction'];

    public const CALCS = ['fixed' => 'Fixed amount', 'percent' => '% of basic'];

    protected $fillable = ['name', 'type', 'calc', 'default_value', 'is_active'];

    protected $casts = [
        'default_value' => 'float',
        'is_active' => 'boolean',
    ];
}
