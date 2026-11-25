<?php

namespace Database\Factories;

use App\Models\PublicContentTranslationMedia;
use App\Models\PublicContentTranslationRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicContentTranslationMedia>
 */
class PublicContentTranslationMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_content_translation_revision_id' => PublicContentTranslationRevision::factory(),
            // Callers must replace this placeholder with an existing, source-owned
            // MediaAttachment when exercising the foreign-key relationship.
            'media_attachment_id' => 1,
            'alt_text' => null,
            'caption' => null,
        ];
    }
}
