<?php

namespace App\Models;

use Database\Factories\NoticeSlugFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeSlug extends Model
{
    /** @use HasFactory<NoticeSlugFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }
}
