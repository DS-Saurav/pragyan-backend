<?php

namespace App\Models;

use Database\Factories\AdmissionCycleGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionCycleGrade extends Model
{
    /** @use HasFactory<AdmissionCycleGradeFactory> */
    use HasFactory;

    protected $fillable = ['admission_cycle_id', 'grade', 'sort_order'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }
}
