<?php

namespace Database\Factories;

use App\Models\PublicContentTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicContentTranslation>
 */
class PublicContentTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_type' => 'news_story',
            'content_id' => 1,
            'locale' => PublicContentTranslation::LOCALE_NEPALI,
            'publication_state' => 'draft',
            'published_revision_id' => null,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => null,
            'withdrawn_at' => null,
            'archived_at' => null,
            'created_by' => null,
            'updated_by' => null,
            'published_by' => null,
        ];
    }
}
