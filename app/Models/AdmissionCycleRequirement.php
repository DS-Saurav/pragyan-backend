<?php

namespace App\Models;

use Database\Factories\AdmissionCycleRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionCycleRequirement extends Model
{
    /** @use HasFactory<AdmissionCycleRequirementFactory> */
    use HasFactory;

    protected $fillable = ['admission_cycle_id', 'label', 'detail', 'sort_order'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }
}
