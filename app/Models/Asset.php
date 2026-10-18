<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'asset_tag',
        'name',
        'category',
        'location',
        'purchase_date',
        'cost',
        'quantity',
        'available_quantity',
        'maintenance_logs_count',
        'maintenance_cost',
        'status',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(AssetLoan::class)->where('status', 'issued');
    }
}
