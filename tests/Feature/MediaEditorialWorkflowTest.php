<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\NewsStory;
use App\Models\NewsStoryRevision;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Media\MediaLibrary;
use App\Services\Media\MediaUsage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaEditorialWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            DB::purge('sqlite');
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Editorial media tests require isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media_originals');
        Storage::fake('media_derivatives');
        Storage::fake('public');
    }

    public function test_publication_requires_meaningful_alt_text_unless_the_asset_is_decorative(): void
    {
        $admin = $this->user();
        $missingAlt = $this->draftAsset($admin, ['default_alt_text' => null]);
        $this->library()->submit($admin, $missingAlt);
        $this->library()->approve($admin, $missingAlt);
        $this->rejects(fn () => $this->library()->publish($admin, $missingAlt), 'alt text is required');

        $decorative = $this->draftAsset($admin, [
            'default_alt_text' => null,
            'is_decorative' => true,
        ]);
        $this->library()->submit($admin, $decorative);
        $this->library()->approve($admin, $decorative);

        $this->assertSame('published', $this->library()->publish($admin, $decorative)->status);
    }

    public function test_confirmed_and_withdrawn_consent_are_audited_and_withdrawal_hides_the_derivative(): void
    {
        $admin = $this->user();
        $asset = $this->publishedAsset($admin);

        $confirmed = $this->library()->confirmConsent($admin, $asset);
        $this->assertSame('confirmed', $confirmed->consent_state);
        $this->assertEquals($admin->id, $confirmed->consent_confirmed_by);
        $this->assertNotNull($confirmed->consent_confirmed_at);
        $this->get(route('media.published', $asset))->assertOk();

        $withdrawn = $this->library()->withdrawConsent($admin, $asset);
        $this->assertSame('withdrawn', $withdrawn->consent_state);
        $this->assertEquals($admin->id, $withdrawn->consent_withdrawn_by);
        $this->assertNotNull($withdrawn->consent_withdrawn_at);
        $this->get(route('media.published', $asset))->assertNotFound();
    }

    public function test_granular_media_editor_can_open_the_library_but_cannot_open_a_private_original_without_media_view(): void
    {
        $admin = $this->user();
        $asset = $this->draftAsset($admin);
        config(['permissions.roles.teacher' => ['media.upload']]);
        $editor = $this->user('teacher');

        $this->actingAs($editor)->get(route('admin.media.index'))->assertOk();
        $this->get(route('admin.media.original', $asset))->assertForbidden();
    }

    public function test_news_author_can_replace_a_draft_cover_without_media_permissions_but_not_a_non_draft_cover(): void
    {
        $admin = $this->user();
        $firstAsset = $this->publishedAsset($admin);
        $replacementAsset = $this->publishedAsset($admin);
        config(['permissions.roles.teacher' => ['news.author']]);
        $author = $this->user('teacher');
        $unauthorized = $this->user('principal');
        $story = NewsStory::factory()->create();
        $draft = NewsStoryRevision::factory()->create([
            'news_story_id' => $story->id,
            'workflow_state' => 'draft',
        ]);

        $this->assertFalse($author->can('media.publish'));
        $attachment = $this->library()->replaceNewsStoryRevisionCover($author, $draft, $firstAsset, [
            'caption' => 'First cover',
            'alt_text' => 'Students building a robot',
            'focal_x' => 35,
            'focal_y' => 45,
        ]);
        $this->assertSame('news-story-revision', $attachment->attachable_type);
        $this->assertSame($draft->id, $attachment->attachable_id);
        $this->assertSame($firstAsset->id, $attachment->media_asset_id);

        $replacement = $this->library()->replaceNewsStoryRevisionCover($author, $draft, $replacementAsset, [
            'caption' => 'Replacement cover',
            'alt_text' => 'Students testing the robot',
        ]);
        $this->assertSame($attachment->id, $replacement->id);
        $this->assertSame($replacementAsset->id, $replacement->media_asset_id);
        $this->assertSame('Replacement cover', $replacement->caption);

        try {
            $this->library()->replaceNewsStoryRevisionCover($unauthorized, $draft, $firstAsset);
            $this->fail('An account without news.author selected a draft cover.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        }

        $reviewRevision = NewsStoryRevision::factory()->create([
            'news_story_id' => $story->id,
            'revision_number' => 2,
            'workflow_state' => 'review',
        ]);
        $this->rejects(
            fn () => $this->library()->replaceNewsStoryRevisionCover($author, $reviewRevision, $firstAsset),
            'draft News revision',
        );
    }

    public function test_current_published_and_scheduled_news_covers_block_media_lifecycle_changes(): void
    {
        $admin = $this->user();
        $publishedAsset = $this->publishedAsset($admin);
        $publishedStory = NewsStory::factory()->create(['publication_state' => 'published']);
        $publishedRevision = NewsStoryRevision::factory()->create([
            'news_story_id' => $publishedStory->id,
            'workflow_state' => 'published',
            'published_at' => now(),
        ]);
        $publishedStory->update([
            'published_revision_id' => $publishedRevision->id,
            'published_at' => now(),
        ]);
        $publishedCover = $this->attachNewsCover($publishedAsset, $publishedRevision);
        $this->assertCurrentNewsCoverBlocksLifecycle($admin, $publishedAsset, $publishedCover);

        $scheduledAsset = $this->publishedAsset($admin);
        $scheduledStory = NewsStory::factory()->create([
            'publication_state' => 'review',
            'scheduled_for' => now()->addDay(),
        ]);
        $scheduledRevision = NewsStoryRevision::factory()->create([
            'news_story_id' => $scheduledStory->id,
            'workflow_state' => 'scheduled',
            'published_at' => null,
        ]);
        $scheduledStory->update(['scheduled_revision_id' => $scheduledRevision->id]);
        $scheduledCover = $this->attachNewsCover($scheduledAsset, $scheduledRevision);
        $this->assertCurrentNewsCoverBlocksLifecycle($admin, $scheduledAsset, $scheduledCover);
    }

    public function test_consent_withdrawal_withdraws_current_news_clears_featured_reference_and_invalidates_news_cache(): void
    {
        $admin = $this->user();
        $asset = $this->publishedAsset($admin);
        $story = NewsStory::factory()->create(['publication_state' => 'published']);
        $revision = NewsStoryRevision::factory()->create([
            'news_story_id' => $story->id,
            'workflow_state' => 'published',
            'published_at' => now(),
        ]);
        $story->update([
            'published_revision_id' => $revision->id,
            'published_at' => now(),
        ]);
        $this->attachNewsCover($asset, $revision);
        Setting::set('website_featured_news_story_id', (string) $story->id);
        Cache::put('public-news:available', true, now()->addMinutes(5));
        Cache::put('public-news:featured', true, now()->addMinutes(5));

        $this->library()->withdrawConsent($admin, $asset);

        $this->assertSame('withdrawn', $story->fresh()->publication_state);
        $this->assertNotNull($story->fresh()->withdrawn_at);
        $this->assertNull(Setting::get('website_featured_news_story_id'));
        $this->assertFalse(Cache::has('public-news:available'));
        $this->assertFalse(Cache::has('public-news:featured'));
        $this->get(route('media.published', $asset))->assertNotFound();
    }

    public function test_consent_withdrawal_cancels_an_unsafe_scheduled_replacement_without_withdrawing_the_older_live_story(): void
    {
        $admin = $this->user();
        $liveAsset = $this->publishedAsset($admin);
        $scheduledAsset = $this->publishedAsset($admin);
        $story = NewsStory::factory()->create(['publication_state' => 'published']);
        $liveRevision = NewsStoryRevision::factory()->create([
            'news_story_id' => $story->id,
            'workflow_state' => 'published',
            'published_at' => now(),
        ]);
        $scheduledRevision = NewsStoryRevision::factory()->create([
            'news_story_id' => $story->id,
            'revision_number' => 2,
            'workflow_state' => 'scheduled',
        ]);
        $story->update([
            'published_revision_id' => $liveRevision->id,
            'published_at' => now(),
            'scheduled_revision_id' => $scheduledRevision->id,
            'scheduled_for' => now()->addDay(),
        ]);
        $this->attachNewsCover($liveAsset, $liveRevision);
        $this->attachNewsCover($scheduledAsset, $scheduledRevision);

        $this->library()->withdrawConsent($admin, $scheduledAsset);

        $this->assertSame('published', $story->fresh()->publication_state);
        $this->assertSame($liveRevision->id, $story->fresh()->published_revision_id);
        $this->assertNull($story->fresh()->scheduled_revision_id);
        $this->assertNull($story->fresh()->scheduled_for);
        $this->assertSame('approved', $scheduledRevision->fresh()->workflow_state);
        $this->get(route('media.published', $scheduledAsset))->assertNotFound();
    }

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function library(): MediaLibrary
    {
        return app(MediaLibrary::class);
    }

    /** @param array<string, mixed> $metadata */
    private function draftAsset(User $actor, array $metadata = []): MediaAsset
    {
        return $this->library()->upload($actor, UploadedFile::fake()->image('editorial.jpg', 640, 480), array_replace([
            'title' => 'Editorial school image',
            'default_alt_text' => 'Students working together in a classroom',
            'category' => 'news',
        ], $metadata));
    }

    private function publishedAsset(User $admin): MediaAsset
    {
        $asset = $this->draftAsset($admin);
        $this->library()->submit($admin, $asset);
        $this->library()->approve($admin, $asset);

        return $this->library()->publish($admin, $asset);
    }

    private function attachNewsCover(MediaAsset $asset, NewsStoryRevision $revision): MediaAttachment
    {
        return $asset->attachments()->create([
            'attachable_type' => 'news-story-revision',
            'attachable_id' => $revision->id,
            'placement' => 'cover',
            'display_order' => 0,
            'alt_text' => 'Students participating in school life',
        ]);
    }

    private function assertCurrentNewsCoverBlocksLifecycle(User $actor, MediaAsset $asset, MediaAttachment $cover): void
    {
        $uses = app(MediaUsage::class)->publishedUses($asset);
        $this->assertNotEmpty($uses);
        $this->assertStringContainsString('news-story-revision:'.$cover->attachable_id, $uses[0]);
        $this->rejects(fn () => $this->library()->draft($actor, $asset), 'news-story-revision');
        $this->rejects(fn () => $this->library()->archive($actor, $asset), 'news-story-revision');
        $this->rejects(fn () => $this->library()->detach($actor, $cover), 'News editor');
        $this->assertSame('published', $asset->fresh()->status);
        $this->assertNull($asset->fresh()->archived_at);
    }

    private function rejects(callable $action, string $contains): void
    {
        try {
            $action();
            $this->fail('Expected validation denial.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($contains, json_encode($exception->errors()));
        }
    }
}
