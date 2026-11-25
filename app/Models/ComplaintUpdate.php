<?php

namespace App\Models;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Model;

class ComplaintUpdate extends Model
{
    protected $table = 'school_complaint_updates';

    protected $guarded = [];

    protected $casts = ['is_internal' => 'boolean'];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class, 'complaint_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
