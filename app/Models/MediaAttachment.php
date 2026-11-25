<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaAttachment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'focal_x' => 'decimal:2', 'focal_y' => 'decimal:2'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
