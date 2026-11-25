<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolActivity extends Model
{
    use HasFactory;

    protected $table = 'school_activities';

    protected $fillable = [
        'activity_date',
        'title',
        'type',
        'in_charge_id',
        'status',
    ];

    protected $casts = [
        'activity_date' => 'date',
    ];

    public function inCharge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'in_charge_id');
    }
}
