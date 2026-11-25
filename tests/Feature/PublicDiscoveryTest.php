<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\NewsStory;
use App\Models\NewsStorySlug;
use App\Models\PublicPage;
use App\Models\PublicPageRevision;
use App\Models\PublicTeamProfile;
use App\Models\PublicTeamProfileRevision;
use App\Models\Setting;
use App\Models\StaffMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://public.pragyan.test']);
    }

    public function test_sitemap_lists_only_current_public_canonical_destinations_from_the_configured_origin(): void
    {
        $story = NewsStory::factory()->published()->create(['slug' => 'learning-celebration']);
        $album = GalleryAlbum::factory()->published()->create(['slug' => 'science-fair']);
        NewsStorySlug::create([
            'news_story_id' => $story->id,
            'slug' => 'former-learning-celebration',
        ]);
        NewsStory::factory()->create([
            'slug' => 'future-private-story',
            'publication_state' => 'scheduled',
            'scheduled_for' => now()->addDay(),
        ]);
        GalleryAlbum::factory()->create([
            'slug' => 'private-gallery-draft',
            'publication_state' => 'draft',
        ]);

        $this->publishedPage(PublicPage::TYPE_ABOUT);
        $this->publishedTeamProfile();

        $response = $this->withHeader('Host', 'untrusted-host.example')->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>https://public.pragyan.test/</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/admissions</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/contact</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/calendar</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/notices</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/about</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/team</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/news</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/news/learning-celebration</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/gallery</loc>', false)
            ->assertSee('<loc>https://public.pragyan.test/gallery/science-fair</loc>', false)
            ->assertDontSee('untrusted-host.example', false)
            ->assertDontSee('/academics</loc>', false)
            ->assertDontSee('former-learning-celebration', false)
            ->assertDontSee('future-private-story', false)
            ->assertDontSee('private-gallery-draft', false)
            ->assertDontSee('/news/archive', false)
            ->assertDontSee('/gallery/archive', false)
            ->assertDontSee('/notices/archive', false);

        $this->assertStringContainsString(
            $story->fresh()->published_at->toAtomString(),
            $response->getContent(),
        );
        $this->assertStringContainsString(
            $album->fresh()->published_at->toAtomString(),
            $response->getContent(),
        );
    }

    public function test_sitemap_hides_empty_optional_sections_and_robots_references_the_same_canonical_sitemap(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('<loc>https://public.pragyan.test/about</loc>', false)
            ->assertDontSee('<loc>https://public.pragyan.test/academics</loc>', false)
            ->assertDontSee('<loc>https://public.pragyan.test/team</loc>', false)
            ->assertDontSee('<loc>https://public.pragyan.test/news</loc>', false)
            ->assertDontSee('<loc>https://public.pragyan.test/gallery</loc>', false);

        $this->withHeader('Host', 'untrusted-host.example')->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin/', false)
            ->assertSee('Disallow: /media/', false)
            ->assertSee('Disallow: /news/archive', false)
            ->assertSee('Sitemap: https://public.pragyan.test/sitemap.xml', false)
            ->assertDontSee('untrusted-host.example', false);
    }

    public function test_public_metadata_uses_the_configured_origin_and_never_falls_back_to_legacy_storage_heroes(): void
    {
        Setting::set('website_hero_images', json_encode([
            ['path' => 'website/heroes/legacy-hero.jpg', 'alt' => 'A legacy hero'],
        ]));
        Setting::set('school_name', 'Pragyan Pathashala');
        Setting::set('phone', '01-5550000');
        Setting::set('email', 'office@pragyan.example');

        $this->withHeader('Host', 'untrusted-host.example')->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://public.pragyan.test/">', false)
            ->assertSee('<meta property="og:url" content="https://public.pragyan.test/">', false)
            ->assertSee('"@type":"EducationalOrganization"', false)
            ->assertSee('"url":"https://public.pragyan.test/"', false)
            ->assertDontSee('<meta name="csrf-token"', false)
            ->assertDontSee('<meta property="og:image"', false)
            ->assertDontSee('untrusted-host.example', false);
    }

    private function publishedPage(string $type): PublicPage
    {
        $page = PublicPage::factory()->create(['type' => $type]);
        $revision = PublicPageRevision::factory()->create([
            'public_page_id' => $page->id,
            'workflow_state' => 'published',
            'published_at' => now(),
        ]);
        $page->update([
            'publication_state' => 'published',
            'published_revision_id' => $revision->id,
            'published_at' => $revision->published_at,
        ]);

        return $page->fresh();
    }

    private function publishedTeamProfile(): PublicTeamProfile
    {
        $staff = StaffMember::create([
            'name' => 'Approved staff member',
            'designation' => 'Teacher',
            'type' => 'teaching',
            'basic_salary' => 0,
            'status' => 'active',
        ]);
        $profile = PublicTeamProfile::factory()->create();
        $revision = PublicTeamProfileRevision::factory()->create([
            'public_team_profile_id' => $profile->id,
            'staff_member_id' => $staff->id,
            'workflow_state' => 'published',
            'published_at' => now(),
        ]);
        $profile->update([
            'publication_state' => 'published',
            'published_revision_id' => $revision->id,
            'published_at' => $revision->published_at,
        ]);

        return $profile->fresh();
    }
}
