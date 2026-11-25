<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConductRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'recorded_by',
        'entry_date',
        'type',
        'points',
        'note',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
