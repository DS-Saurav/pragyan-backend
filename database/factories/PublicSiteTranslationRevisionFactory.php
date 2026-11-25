<?php

namespace Database\Factories;

use App\Models\PublicSiteTranslation;
use App\Models\PublicSiteTranslationRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicSiteTranslationRevision>
 */
class PublicSiteTranslationRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_site_translation_id' => PublicSiteTranslation::factory(),
            'revision_number' => 1,
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
