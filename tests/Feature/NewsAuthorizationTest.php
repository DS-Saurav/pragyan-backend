<?php

namespace Tests\Feature;

use App\Models\NewsStory;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_staff_without_an_editorial_capability_cannot_open_news_administration(): void
    {
        $this->get(route('admin.news.index'))->assertRedirect(route('login'));

        $this->actingAs($this->userWithPermissions([]))
            ->get(route('admin.news.index'))
            ->assertForbidden();
    }

    public function test_author_can_create_private_news_but_cannot_review_or_publish_it(): void
    {
        $author = $this->userWithPermissions(['news.author']);

        $this->actingAs($author)
            ->get(route('admin.news.create'))
            ->assertOk();

        $this->actingAs($author)
            ->post(route('admin.news.store'), $this->storyPayload())
            ->assertRedirect();

        $story = NewsStory::query()->sole();
        $revision = $story->revisions()->sole();

        $this->assertSame('draft', $story->publication_state);
        $this->assertSame('draft', $revision->workflow_state);

        $this->actingAs($author)
            ->patch(route('admin.news.approve', [$story, $revision]))
            ->assertForbidden();

        $this->actingAs($author)
            ->patch(route('admin.news.publish', [$story, $revision]))
            ->assertForbidden();
    }

    public function test_reviewer_and_publisher_can_inspect_news_but_do_not_gain_author_access(): void
    {
        foreach (['news.review', 'news.publish'] as $permission) {
            $user = $this->userWithPermissions([$permission]);

            $this->actingAs($user)
                ->get(route('admin.news.index'))
                ->assertOk();

            $this->actingAs($user)
                ->get(route('admin.news.create'))
                ->assertForbidden();
        }
    }

    public function test_news_author_permission_does_not_grant_internal_admissions_access(): void
    {
        $author = $this->userWithPermissions(['news.author']);

        $this->actingAs($author)
            ->get(route('admin.admissions.index'))
            ->assertForbidden();
    }

    /** @param array<int, string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        config()->set('permissions.roles.teacher', $permissions);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'teacher']);

        return $user;
    }

    /** @return array<string, string> */
    private function storyPayload(): array
    {
        return [
            'title' => 'A learning story',
            'slug' => 'a-learning-story',
            'summary' => 'A concise public summary for families.',
            'body_markdown' => "# Welcome\n\nA carefully prepared private draft.",
            'category' => 'Learning',
            'public_attribution' => 'Pragyan Pathashala',
        ];
    }
}
