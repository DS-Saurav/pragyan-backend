<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'quantity',
        'reorder_level',
        'unit',
    ];

    /**
     * Get the movement audit history for this inventory item.
     */
    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id')->latest();
    }
}
