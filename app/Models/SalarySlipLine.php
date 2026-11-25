<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalarySlipLine extends Model
{
    protected $fillable = ['salary_slip_id', 'type', 'name', 'amount', 'is_system'];

    protected $casts = [
        'amount' => 'float',
        'is_system' => 'boolean',
    ];

    public function slip(): BelongsTo
    {
        return $this->belongsTo(SalarySlip::class, 'salary_slip_id');
    }
}
