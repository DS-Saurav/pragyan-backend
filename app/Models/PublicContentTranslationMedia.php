<?php

namespace App\Models;

use Database\Factories\PublicContentTranslationMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicContentTranslationMedia extends Model
{
    /** @use HasFactory<PublicContentTranslationMediaFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function translationRevision(): BelongsTo
    {
        return $this->belongsTo(PublicContentTranslationRevision::class, 'public_content_translation_revision_id');
    }

    public function mediaAttachment(): BelongsTo
    {
        return $this->belongsTo(MediaAttachment::class);
    }
}
