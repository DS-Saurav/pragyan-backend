<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeHead extends Model
{
    public const FREQUENCIES = [
        'monthly' => 'Monthly',
        'termly' => 'Per term',
        'yearly' => 'Yearly',
        'one_time' => 'One time',
    ];

    protected $fillable = ['name', 'frequency', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }
}
