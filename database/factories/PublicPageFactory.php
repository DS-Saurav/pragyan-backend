<?php

namespace Database\Factories;

use App\Models\PublicPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicPage>
 */
class PublicPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => PublicPage::TYPE_ABOUT,
            'publication_state' => 'draft',
            'published_revision_id' => null,
            'scheduled_revision_id' => null,
            'scheduled_for' => null,
            'published_at' => null,
            'withdrawn_at' => null,
            'archived_at' => null,
        ];
    }

    public function about(): static
    {
        return $this->state(fn (): array => ['type' => PublicPage::TYPE_ABOUT]);
    }

    public function academics(): static
    {
        return $this->state(fn (): array => ['type' => PublicPage::TYPE_ACADEMICS]);
    }
}
