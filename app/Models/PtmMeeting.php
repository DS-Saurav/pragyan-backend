<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PtmMeeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'school_class_id',
        'meeting_date',
        'time_slot',
        'venue',
        'agenda',
        'status',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }
}
