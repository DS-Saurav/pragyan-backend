<?php

namespace App\Models;

use Database\Factories\AdmissionCycleFaqFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionCycleFaq extends Model
{
    /** @use HasFactory<AdmissionCycleFaqFactory> */
    use HasFactory;

    protected $fillable = ['admission_cycle_id', 'question', 'answer', 'sort_order'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }
}
