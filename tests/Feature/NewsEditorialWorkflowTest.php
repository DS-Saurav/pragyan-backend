<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\NewsStoryRevision;
use App\Models\User;
use App\Models\UserRole;
use App\Services\News\NewsPublisher;
use App\Services\News\NewsVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NewsEditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_revision_remains_live_until_a_scheduled_replacement_is_due(): void
    {
        config(['permissions.roles.teacher' => ['news.author', 'news.review', 'news.publish']]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(NewsPublisher::class);
        $story = $publisher->createDraft($author, $this->draftData());
        $firstRevision = $story->revisions->firstOrFail();
        $this->attachCover($firstRevision, $this->coverAsset($reviewer));
        $publisher->submit($author, $story, $firstRevision);
        $publisher->approve($reviewer, $story, $firstRevision);
        $publisher->publish($reviewer, $story, $firstRevision);

        $story = $story->fresh();
        $this->assertSame('published', $story->publication_state);
        $this->assertSame($firstRevision->id, $story->published_revision_id);
        $this->assertTrue(app(NewsVisibility::class)->hasPublished());

        $replacement = $publisher->saveDraft($author, $story, $this->draftData([
            'slug' => 'new-term-learning',
            'title' => 'New Term Learning',
        ]));
        $this->assertSame('draft', $replacement->workflow_state);
        $this->assertSame($firstRevision->id, $story->fresh()->published_revision_id);
        $this->assertSame('new-term-learning', $replacement->slug);
        $this->attachCover($replacement, $this->coverAsset($reviewer));
        $publisher->submit($author, $story, $replacement);
        $publisher->approve($reviewer, $story, $replacement);

        $scheduledFor = CarbonImmutable::now(NewsPublisher::TIMEZONE)->addMinutes(10);
        $publisher->publish($reviewer, $story, $replacement, $scheduledFor);

        $scheduledStory = $story->fresh();
        $this->assertSame($firstRevision->id, $scheduledStory->published_revision_id);
        $this->assertSame($replacement->id, $scheduledStory->scheduled_revision_id);
        $this->assertSame('scheduled', $replacement->fresh()->workflow_state);
        $this->assertSame($story->slug, app(NewsVisibility::class)->findDisplayableSlug($story->slug)['story']?->slug);

        $publisher->releaseDue($scheduledFor->addSecond());

        $released = $story->fresh();
        $this->assertSame($replacement->id, $released->published_revision_id);
        $this->assertSame('new-term-learning', $released->slug);
        $this->assertDatabaseHas('news_story_slugs', [
            'news_story_id' => $released->id,
            'slug' => 'learning-at-pragyan',
        ]);
        $former = app(NewsVisibility::class)->findDisplayableSlug('learning-at-pragyan', $scheduledFor->addSecond());
        $this->assertSame($released->id, $former['story']?->id);
        $this->assertSame($released->id, $former['redirect']?->id);
    }

    public function test_publish_rejects_a_cover_without_current_public_consent(): void
    {
        config(['permissions.roles.teacher' => ['news.author', 'news.review', 'news.publish']]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(NewsPublisher::class);
        $story = $publisher->createDraft($author, $this->draftData());
        $revision = $story->revisions->firstOrFail();
        $asset = $this->coverAsset($reviewer);
        $this->attachCover($revision, $asset);
        $publisher->submit($author, $story, $revision);
        $publisher->approve($reviewer, $story, $revision);
        $asset->update([
            'consent_state' => 'withdrawn',
            'consent_withdrawn_at' => now(),
        ]);

        try {
            $publisher->publish($reviewer, $story, $revision);
            $this->fail('A withdrawn media consent must prevent publication.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('consent', json_encode($exception->errors()));
        }

        $this->assertSame('approved', $revision->fresh()->workflow_state);
        $this->assertSame('review', $story->fresh()->publication_state);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'teacher']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, string|null>
     */
    private function draftData(array $overrides = []): array
    {
        return array_replace([
            'slug' => 'learning-at-pragyan',
            'title' => 'Learning at Pragyan',
            'summary' => 'A look at joyful learning in the new school term.',
            'body_markdown' => "## A fresh term\n\nStudents are learning together.",
            'category' => 'school-life',
            'public_attribution' => 'Pragyan Pathashala',
        ], $overrides);
    }

    private function coverAsset(User $owner): MediaAsset
    {
        $filename = (string) Str::uuid();

        return MediaAsset::create([
            'disk' => 'media_originals',
            'original_path' => 'originals/'.$filename.'.jpg',
            'public_disk' => 'media_derivatives',
            'public_path' => 'published/'.$filename.'.jpg',
            'original_filename' => 'cover.jpg',
            'storage_filename' => $filename.'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
            'width' => 1200,
            'height' => 800,
            'checksum' => hash('sha256', $filename),
            'title' => 'News cover',
            'default_alt_text' => 'Students collaborating in class.',
            'category' => 'news',
            'uploaded_by' => $owner->id,
            'status' => 'published',
            'published_by' => $owner->id,
            'published_at' => now(),
            'consent_state' => 'confirmed',
            'consent_confirmed_by' => $owner->id,
            'consent_confirmed_at' => now(),
        ]);
    }

    private function attachCover(NewsStoryRevision $revision, MediaAsset $asset): MediaAttachment
    {
        return MediaAttachment::create([
            'media_asset_id' => $asset->id,
            'attachable_type' => 'news-story-revision',
            'attachable_id' => $revision->id,
            'placement' => 'cover',
            'display_order' => 0,
            'alt_text' => 'Students collaborating in class.',
        ]);
    }
}
