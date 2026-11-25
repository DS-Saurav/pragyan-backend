<?php

namespace Database\Factories;

use App\Models\PublicContentTranslation;
use App\Models\PublicContentTranslationRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicContentTranslationRevision>
 */
class PublicContentTranslationRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_content_translation_id' => PublicContentTranslation::factory(),
            'revision_number' => 1,
            'source_revision_id' => null,
            'source_fingerprint' => null,
            'payload' => [],
            'workflow_state' => 'draft',
            'created_by' => null,
            'updated_by' => null,
            'submitted_by' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'published_by' => null,
            'published_at' => null,
        ];
    }
}
