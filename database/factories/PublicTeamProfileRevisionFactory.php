<?php

namespace Database\Factories;

use App\Models\PublicTeamProfile;
use App\Models\PublicTeamProfileRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicTeamProfileRevision>
 */
class PublicTeamProfileRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_team_profile_id' => PublicTeamProfile::factory(),
            'revision_number' => 1,
            'staff_member_id' => null,
            'display_name' => fake()->name(),
            'public_role' => 'Teacher',
            'team_group' => 'teaching',
            'qualification' => fake()->sentence(3),
            'bio_markdown' => fake()->paragraph(),
            'display_order' => 0,
            'workflow_state' => 'draft',
            'submitted_at' => null,
            'reviewed_at' => null,
            'published_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'workflow_state' => 'approved',
            'reviewed_at' => now('Asia/Kathmandu'),
        ]);
    }
}
