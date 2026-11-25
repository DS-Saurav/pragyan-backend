<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpdeskReply extends Model
{
    protected $guarded = [];

    protected $casts = ['is_internal' => 'boolean'];

    public function ticket()
    {
        return $this->belongsTo(HelpdeskTicket::class, 'helpdesk_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
