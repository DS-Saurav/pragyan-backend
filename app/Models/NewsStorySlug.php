<?php

namespace App\Models;

use Database\Factories\NewsStorySlugFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsStorySlug extends Model
{
    /** @use HasFactory<NewsStorySlugFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function story(): BelongsTo
    {
        return $this->belongsTo(NewsStory::class, 'news_story_id');
    }
}
