<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\NewsStory;
use App\Models\NewsStoryRevision;
use App\Services\Gallery\GalleryPublisher;
use App\Services\News\NewsPublisher;
use App\Services\Notices\NoticePublisher;
use App\Services\PublicPages\PublicPagePublisher;
use App\Services\PublicTeam\PublicTeamProfilePublisher;
use App\Support\PublicNavigation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReleaseDuePublicContentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_command_releases_due_news_without_a_public_request_and_invalidates_public_visibility_cache(): void
    {
        $now = $this->freezeKathmanduTime();
        [$story, $revision] = $this->dueNewsStory('due-news-command', 'Due News Command', true, $now);
        Cache::put('public-news:available', true);
        Cache::put('public-news:featured', $story->id);

        $this->assertFalse($this->navigationHasLink('News'));

        $this->artisan('public-content:release-due')
            ->expectsOutput('News: checked.')
            ->assertSuccessful();

        $released = $story->fresh();
        $this->assertSame('published', $released->publication_state);
        $this->assertSame($revision->id, $released->published_revision_id);
        $this->assertNull($released->scheduled_revision_id);
        $this->assertNull($released->scheduled_for);
        $this->assertSame('published', $revision->fresh()->workflow_state);
        $this->assertTrue($this->navigationHasLink('News'));
        $this->assertFalse(Cache::has('public-news:available'));
        $this->assertFalse(Cache::has('public-news:featured'));

        $publishedAt = $released->published_at?->getTimestamp();

        $this->artisan('public-content:release-due')->assertSuccessful();

        $again = $story->fresh();
        $this->assertSame($revision->id, $again->published_revision_id);
        $this->assertNull($again->scheduled_revision_id);
        $this->assertSame($publishedAt, $again->published_at?->getTimestamp());
        $this->assertSame(1, $story->revisions()->count());
    }

    public function test_command_keeps_live_news_public_when_a_due_replacement_loses_media_consent(): void
    {
        $now = $this->freezeKathmanduTime();
        $story = NewsStory::factory()->published()->create(['slug' => 'live-story']);
        $liveRevisionId = $story->fresh()->published_revision_id;
        [$replacement, $replacementRevision] = $this->dueNewsStory(
            'unsafe-replacement',
            'Unsafe replacement',
            false,
            $now,
            $story,
            2,
        );

        $this->assertSame($story->id, $replacement->id);

        $this->artisan('public-content:release-due')->assertSuccessful();

        $afterRelease = $story->fresh();
        $this->assertSame('published', $afterRelease->publication_state);
        $this->assertSame($liveRevisionId, $afterRelease->published_revision_id);
        $this->assertNull($afterRelease->scheduled_revision_id);
        $this->assertNull($afterRelease->scheduled_for);
        $this->assertSame('approved', $replacementRevision->fresh()->workflow_state);
    }

    public function test_command_leaves_a_due_new_story_unpublished_when_its_media_consent_is_withdrawn(): void
    {
        $now = $this->freezeKathmanduTime();
        [$story, $revision] = $this->dueNewsStory('unsafe-new-story', 'Unsafe new story', false, $now);

        $this->artisan('public-content:release-due')->assertSuccessful();

        $afterRelease = $story->fresh();
        $this->assertSame('draft', $afterRelease->publication_state);
        $this->assertNull($afterRelease->published_revision_id);
        $this->assertNull($afterRelease->scheduled_revision_id);
        $this->assertNull($afterRelease->scheduled_for);
        $this->assertSame('approved', $revision->fresh()->workflow_state);
        $this->assertFalse($this->navigationHasLink('News'));
    }

    public function test_public_get_does_not_release_due_content_or_issue_database_write_queries(): void
    {
        $now = $this->freezeKathmanduTime();
        [$story, $revision] = $this->dueNewsStory('visitor-must-not-release', 'Visitor must not release', true, $now);
        $writeStatements = [];

        DB::listen(function (QueryExecuted $query) use (&$writeStatements): void {
            if (preg_match('/^\s*(?:insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query->sql) === 1) {
                $writeStatements[] = $query->sql;
            }
        });

        $this->get(route('public.news'))
            ->assertOk()
            ->assertDontSee('Visitor must not release');

        $this->assertSame([], $writeStatements);
        $this->assertSame('draft', $story->fresh()->publication_state);
        $this->assertSame($revision->id, $story->fresh()->scheduled_revision_id);
        $this->assertSame('scheduled', $revision->fresh()->workflow_state);
    }

    public function test_command_checks_each_existing_scheduled_content_workflow_at_one_kathmandu_boundary(): void
    {
        $now = $this->freezeKathmanduTime();

        $this->mockPublisher(NewsPublisher::class, $now);
        $this->mockPublisher(GalleryPublisher::class, $now);
        $this->mockPublisher(NoticePublisher::class, $now);
        $this->mockPublisher(PublicPagePublisher::class, $now);
        $this->mockPublisher(PublicTeamProfilePublisher::class, $now);

        $this->artisan('public-content:release-due')->assertSuccessful();
    }

    private function freezeKathmanduTime(): CarbonImmutable
    {
        $now = CarbonImmutable::parse('2026-11-16 09:30:00', 'Asia/Kathmandu');
        CarbonImmutable::setTestNow($now);

        return $now;
    }

    /**
     * @return array{0: NewsStory, 1: NewsStoryRevision}
     */
    private function dueNewsStory(
        string $slug,
        string $title,
        bool $hasCurrentConsent,
        CarbonImmutable $now,
        ?NewsStory $story = null,
        int $revisionNumber = 1,
    ): array {
        $story ??= NewsStory::factory()->create(['slug' => $slug]);
        $revision = NewsStoryRevision::factory()->create([
            'news_story_id' => $story->id,
            'revision_number' => $revisionNumber,
            'slug' => $slug,
            'title' => $title,
            'workflow_state' => 'scheduled',
        ]);
        $asset = $this->coverAsset($hasCurrentConsent);
        MediaAttachment::create([
            'media_asset_id' => $asset->id,
            'attachable_type' => 'news-story-revision',
            'attachable_id' => $revision->id,
            'placement' => 'cover',
            'display_order' => 0,
            'alt_text' => 'Students collaborating in a classroom.',
        ]);
        $story->update([
            'scheduled_revision_id' => $revision->id,
            'scheduled_for' => $now->subSecond()->utc(),
        ]);

        return [$story->fresh(), $revision];
    }

    private function coverAsset(bool $hasCurrentConsent): MediaAsset
    {
        $filename = (string) Str::uuid();

        return MediaAsset::create([
            'disk' => 'media_originals',
            'original_path' => 'originals/'.$filename.'.jpg',
            'public_disk' => 'media_derivatives',
            'public_path' => 'published/'.$filename.'.jpg',
            'original_filename' => 'news-cover.jpg',
            'storage_filename' => $filename.'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
            'width' => 1200,
            'height' => 800,
            'checksum' => hash('sha256', $filename),
            'title' => 'Scheduled news cover',
            'default_alt_text' => 'Students collaborating in a classroom.',
            'category' => 'news',
            'status' => 'published',
            'published_at' => now(),
            'consent_state' => $hasCurrentConsent ? 'confirmed' : 'withdrawn',
            'consent_confirmed_at' => $hasCurrentConsent ? now() : null,
            'consent_withdrawn_at' => $hasCurrentConsent ? null : now(),
        ]);
    }

    private function navigationHasLink(string $label): bool
    {
        foreach (PublicNavigation::groups() as $group) {
            foreach (PublicNavigation::links($group) as $link) {
                if ($link['label'] === $label) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param class-string $publisherClass */
    private function mockPublisher(string $publisherClass, CarbonImmutable $now): void
    {
        $publisher = \Mockery::mock($publisherClass);
        $publisher->shouldReceive('releaseDue')
            ->once()
            ->with(\Mockery::on(function (CarbonInterface $received) use ($now): bool {
                return $received->getTimestamp() === $now->getTimestamp()
                    && $received->getTimezone()->getName() === 'Asia/Kathmandu';
            }));

        $this->app->instance($publisherClass, $publisher);
    }
}
