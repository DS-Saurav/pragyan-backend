<?php

namespace Database\Factories;

use App\Models\PublicTeamProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicTeamProfile>
 */
class PublicTeamProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'publication_state' => 'draft',
            'published_revision_id' => null,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => null,
            'withdrawn_at' => null,
            'archived_at' => null,
        ];
    }
}
