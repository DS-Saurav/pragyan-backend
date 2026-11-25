<?php

namespace App\Models;

use Database\Factories\AdmissionCycleStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionCycleStep extends Model
{
    /** @use HasFactory<AdmissionCycleStepFactory> */
    use HasFactory;

    protected $fillable = ['admission_cycle_id', 'title', 'body', 'sort_order'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }
}
