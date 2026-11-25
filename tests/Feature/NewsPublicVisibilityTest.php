<?php

namespace Tests\Feature;

use App\Models\NewsStory;
use App\Models\NewsStorySlug;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsPublicVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_navigation_and_listing_only_expose_published_stories(): void
    {
        $draft = NewsStory::factory()->create(['slug' => 'private-draft']);
        $live = NewsStory::factory()->published()->create(['slug' => 'community-learning-day']);
        $withdrawn = NewsStory::factory()->published()->create(['slug' => 'withdrawn-story']);
        $withdrawn->update(['publication_state' => 'withdrawn', 'withdrawn_at' => now('Asia/Kathmandu')]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('public.news').'"', false);

        $this->get(route('public.news'))
            ->assertOk()
            ->assertSee($live->publishedRevision->title)
            ->assertDontSee('private-draft')
            ->assertDontSee($withdrawn->publishedRevision->title);

        $this->get(route('public.news.show', $live->slug))
            ->assertOk()
            ->assertSee($live->publishedRevision->title);

        $this->get(route('public.news.show', $draft->slug))->assertNotFound();
        $this->get(route('public.news.show', $withdrawn->slug))->assertNotFound();
    }

    public function test_former_public_slug_redirects_but_nonpublic_story_slugs_do_not(): void
    {
        $story = NewsStory::factory()->published()->create(['slug' => 'new-school-library']);
        NewsStorySlug::create([
            'news_story_id' => $story->id,
            'slug' => 'library-news-before-renovation',
        ]);
        $draft = NewsStory::factory()->create(['slug' => 'private-news-draft']);
        NewsStorySlug::create([
            'news_story_id' => $draft->id,
            'slug' => 'private-news-old-link',
        ]);

        $this->get(route('public.news.show', 'library-news-before-renovation'))
            ->assertRedirect(route('public.news.show', $story->slug))
            ->assertStatus(301);

        $this->get(route('public.news.show', 'private-news-old-link'))->assertNotFound();
    }

    public function test_featured_story_is_optional_and_only_currently_public_content_is_rendered(): void
    {
        $story = NewsStory::factory()->published()->create(['slug' => 'featured-science-story']);
        Setting::set('website_featured_news_story_id', $story->id);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Featured story')
            ->assertSee($story->publishedRevision->title);

        $story->update(['publication_state' => 'withdrawn', 'withdrawn_at' => now('Asia/Kathmandu')]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Featured story')
            ->assertDontSee($story->publishedRevision->title);
    }

    public function test_public_story_body_strips_raw_html_and_the_archive_route_is_available(): void
    {
        $story = NewsStory::factory()->published()->create(['slug' => 'safe-public-story']);
        $story->publishedRevision->update([
            'body_markdown' => "# A safe heading\n\n<script>alert('do not render')</script>\n\n[Unsafe](javascript:alert('x'))",
        ]);

        $this->get(route('public.news.show', $story->slug))
            ->assertOk()
            ->assertSee('A safe heading')
            ->assertDontSee('<script>', false)
            ->assertDontSee('javascript:', false);

        $this->get(route('public.news.archive'))->assertOk();
    }
}
