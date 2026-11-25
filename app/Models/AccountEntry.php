<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountEntry extends Model
{
    use SoftDeletes;

    public const INCOME_CATEGORIES = [
        'Donation', 'Library fine', 'Government grant', 'Canteen / Other sales', 'Hall / Facility rent', 'Other income',
    ];

    public const EXPENSE_CATEGORIES = [
        'Supplies / Stationery', 'Utilities', 'Maintenance / Repair', 'Transport / Fuel',
        'Events / Activities', 'Salary advance', 'Other expense',
    ];

    protected $fillable = [
        'entry_date', 'type', 'category', 'description', 'amount', 'mode', 'reference_no', 'recorded_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'float',
    ];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
