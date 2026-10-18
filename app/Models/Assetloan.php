<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLoan extends Model
{
    protected $fillable = [
        'asset_id',
        'student_id',
        'issue_date',
        'due_date',
        'status',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}