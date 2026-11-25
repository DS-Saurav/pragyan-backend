<?php

namespace Tests\Feature;

use App\Models\PublicPage;
use App\Models\PublicPageRevision;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PublicPages\PublicPageMarkdown;
use App\Services\PublicPages\PublicPagePublisher;
use App\Services\PublicPages\PublicPageVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicPageEditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_and_publish_own_about_page_after_submission(): void
    {
        $admin = $this->editor('admin');
        $publisher = app(PublicPagePublisher::class);
        $page = $publisher->createDraft($admin, PublicPage::TYPE_ABOUT, $this->data());
        $revision = $page->revisions->sole();

        try {
            $publisher->approve($admin, $page, $revision);
            $this->fail('Even an admin must submit the draft for review first.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Only a revision in review', json_encode($exception->errors()));
        }

        $publisher->submit($admin, $page, $revision);
        $publisher->approve($admin, $page, $revision);
        $this->assertSame($admin->id, $revision->fresh()->reviewed_by);
        $this->assertNotNull($revision->fresh()->reviewed_at);

        $publisher->publish($admin, $page, $revision);
        $this->assertSame('published', $page->fresh()->publication_state);
        $this->assertSame($admin->id, $page->fresh()->published_by);
    }

    public function test_non_admin_page_editor_cannot_approve_own_revision(): void
    {
        $this->allowEditorialWork();
        $author = $this->editor();
        $publisher = app(PublicPagePublisher::class);
        $page = $publisher->createDraft($author, PublicPage::TYPE_ABOUT, $this->data());
        $revision = $page->revisions->sole();
        $publisher->submit($author, $page, $revision);

        try {
            $publisher->approve($author, $page, $revision);
            $this->fail('A non-admin editor must not approve their own revision.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('different authorized reviewer', json_encode($exception->errors()));
        }

        $this->assertSame('review', $revision->fresh()->workflow_state);
        $this->assertNull($revision->fresh()->reviewed_by);
    }

    public function test_live_about_revision_stays_public_until_kathmandu_scheduled_replacement_is_due(): void
    {
        $this->allowEditorialWork();
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(PublicPagePublisher::class);

        $page = $publisher->createDraft($author, PublicPage::TYPE_ABOUT, $this->data([
            'title' => 'About our school today',
            'introduction_markdown' => 'Our currently approved introduction.',
        ]));
        $first = $page->revisions->sole();
        $this->assertSame('draft', $page->publication_state);

        $publisher->submit($author, $page, $first);
        $this->assertSame('review', $first->fresh()->workflow_state);
        $publisher->approve($reviewer, $page, $first);
        $this->assertSame('approved', $first->fresh()->workflow_state);
        $publisher->publish($reviewer, $page, $first);
        $this->assertSame('published', $page->fresh()->publication_state);

        $replacement = $publisher->saveDraft($author, $page, $this->data([
            'title' => 'About our school tomorrow',
            'introduction_markdown' => 'Our approved replacement introduction.',
        ]));
        $this->assertSame($first->id, $page->fresh()->published_revision_id);
        $this->assertSame(
            'About our school today',
            app(PublicPageVisibility::class)->page(PublicPage::TYPE_ABOUT)?->publishedRevision?->title,
        );

        $publisher->submit($author, $page, $replacement);
        $publisher->approve($reviewer, $page, $replacement);
        $scheduledFor = CarbonImmutable::now(PublicPagePublisher::TIMEZONE)->addMinutes(10);
        $this->assertSame(PublicPagePublisher::TIMEZONE, $scheduledFor->getTimezone()->getName());
        $publisher->publish($reviewer, $page, $replacement, $scheduledFor);

        $scheduled = $page->fresh();
        $this->assertSame($first->id, $scheduled->published_revision_id);
        $this->assertSame($replacement->id, $scheduled->scheduled_revision_id);
        $this->assertSame('scheduled', $replacement->fresh()->workflow_state);
        $this->assertSame($scheduledFor->getTimestamp(), $scheduled->scheduled_for?->getTimestamp());
        $this->assertSame(
            'About our school today',
            app(PublicPageVisibility::class)
                ->page(PublicPage::TYPE_ABOUT, $scheduledFor->subSecond())
                ?->publishedRevision?->title,
        );

        $publisher->releaseDue($scheduledFor->addSecond());

        $this->assertSame($replacement->id, $page->fresh()->published_revision_id);
        $this->assertSame('published', $replacement->fresh()->workflow_state);
        $this->assertSame(
            'About our school tomorrow',
            app(PublicPageVisibility::class)
                ->page(PublicPage::TYPE_ABOUT, $scheduledFor->addSecond())
                ?->publishedRevision?->title,
        );
    }

    public function test_withdraw_archive_and_restore_make_a_page_non_public(): void
    {
        $this->allowEditorialWork();
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(PublicPagePublisher::class);
        [$page] = $this->published($publisher, $author, $reviewer, PublicPage::TYPE_ABOUT);

        $publisher->withdraw($reviewer, $page);
        $this->assertSame('withdrawn', $page->fresh()->publication_state);
        $this->assertNull(app(PublicPageVisibility::class)->page(PublicPage::TYPE_ABOUT));

        $publisher->archive($reviewer, $page);
        $archived = $page->fresh();
        $this->assertSame('archived', $archived->publication_state);
        $this->assertNotNull($archived->archived_at);
        $this->assertNull(app(PublicPageVisibility::class)->page(PublicPage::TYPE_ABOUT));

        $publisher->restore($reviewer, $page);
        $restored = $page->fresh();
        $this->assertSame('draft', $restored->publication_state);
        $this->assertNull($restored->archived_at);
        $this->assertNull($restored->published_revision_id);
        $this->assertGreaterThanOrEqual(1, $restored->revisions()->count());
        $this->assertNull(app(PublicPageVisibility::class)->page(PublicPage::TYPE_ABOUT));
    }

    public function test_cancelling_a_scheduled_replacement_keeps_the_existing_live_page_public(): void
    {
        $this->allowEditorialWork();
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(PublicPagePublisher::class);
        [$page, $liveRevision] = $this->published($publisher, $author, $reviewer, PublicPage::TYPE_ABOUT, [
            'title' => 'Still live',
        ]);

        $replacement = $publisher->saveDraft($author, $page, $this->data([
            'title' => 'Cancelled replacement',
            'introduction_markdown' => 'This version must remain private after cancellation.',
        ]));
        $publisher->submit($author, $page, $replacement);
        $publisher->approve($reviewer, $page, $replacement);
        $scheduledFor = CarbonImmutable::now(PublicPagePublisher::TIMEZONE)->addMinutes(10);
        $publisher->publish($reviewer, $page, $replacement, $scheduledFor);

        $publisher->cancelSchedule($reviewer, $page);

        $cancelled = $page->fresh();
        $this->assertSame('published', $cancelled->publication_state);
        $this->assertSame($liveRevision->id, $cancelled->published_revision_id);
        $this->assertNull($cancelled->scheduled_revision_id);
        $this->assertNull($cancelled->scheduled_for);
        $this->assertSame('approved', $replacement->fresh()->workflow_state);
        $this->assertSame(
            'Still live',
            app(PublicPageVisibility::class)
                ->page(PublicPage::TYPE_ABOUT, $scheduledFor->addHour())
                ?->publishedRevision?->title,
        );
    }

    public function test_academics_needs_explicit_public_stages_before_publication(): void
    {
        $this->allowEditorialWork();
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(PublicPagePublisher::class);
        $page = $publisher->createDraft($author, PublicPage::TYPE_ACADEMICS, $this->data([
            'title' => 'Learning at Pragyan',
            'introduction_markdown' => 'Approved public learning information.',
        ]));
        $revision = $page->revisions->sole();
        $publisher->submit($author, $page, $revision);
        $publisher->approve($reviewer, $page, $revision);

        try {
            $publisher->publish($reviewer, $page, $revision);
            $this->fail('Academics must not publish without an explicit public learning stage.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('At least one approved public learning stage', json_encode($exception->errors()));
        }

        $this->assertSame('approved', $revision->fresh()->workflow_state);
        $this->assertNull($page->fresh()->published_revision_id);
    }

    public function test_markdown_strips_raw_html_and_unsafe_link_targets(): void
    {
        $html = app(PublicPageMarkdown::class)->toHtml(<<<'MARKDOWN'
## Approved information

<script data-phase4b-raw="unsafe"></script>

[Do not follow](javascript:alert(1337))

<span data-phase4b-raw="unsafe">Raw attributes must not remain.</span>
MARKDOWN)->toHtml();

        $this->assertStringContainsString('<h2>Approved information</h2>', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertStringNotContainsString('data-phase4b-raw', $html);
        $this->assertStringNotContainsString('href="javascript:', strtolower($html));
    }

    /** @return array{0: PublicPage, 1: PublicPageRevision} */
    private function published(PublicPagePublisher $publisher, User $author, User $reviewer, string $type, array $data = []): array
    {
        $page = $publisher->createDraft($author, $type, $this->data($data));
        $revision = $page->revisions->sole();
        $publisher->submit($author, $page, $revision);
        $publisher->approve($reviewer, $page, $revision);
        $publisher->publish($reviewer, $page, $revision);

        return [$page->fresh(), $revision->fresh()];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function data(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Approved school information',
            'introduction_markdown' => 'A verified public introduction for families.',
            'history_markdown' => 'A concise school history.',
            'mission_markdown' => 'A clear public mission.',
            'vision_markdown' => 'A hopeful public vision.',
            'approach_values' => ['Care', 'Curiosity'],
            'facilities_markdown' => 'Approved facilities information.',
            'stages' => [],
        ], $overrides);
    }

    private function allowEditorialWork(): void
    {
        config(['permissions.roles.teacher' => [
            'public_pages.author',
            'public_pages.review',
            'public_pages.publish',
        ]]);
    }

    private function editor(string $role = 'teacher'): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
