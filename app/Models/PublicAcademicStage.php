<?php

namespace App\Models;

use Database\Factories\PublicAcademicStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicAcademicStage extends Model
{
    /** @use HasFactory<PublicAcademicStageFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'display_order' => 'integer',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(PublicPageRevision::class, 'public_page_revision_id');
    }
}
