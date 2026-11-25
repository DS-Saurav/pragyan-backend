<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\NoticeRevision;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Notices\NoticePublisher;
use App\Services\Notices\NoticeVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NoticeEditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_published_revision_stays_public_until_a_scheduled_replacement_is_due(): void
    {
        $this->grantEditorialPermissions();
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(NoticePublisher::class);

        $notice = $publisher->createDraft($author, $this->draftData());
        $firstRevision = $notice->revisions->sole();
        $publisher->submit($author, $notice, $firstRevision);
        $publisher->approve($reviewer, $notice, $firstRevision);
        $publisher->publish($reviewer, $notice, $firstRevision);

        $live = $notice->fresh();
        $this->assertSame('published', $live->publication_state);
        $this->assertSame($firstRevision->id, $live->published_revision_id);
        $this->assertSame('Term learning update', $live->title);
        $this->assertSame($notice->id, app(NoticeVisibility::class)->findDisplayableSlug('term-learning-update')['notice']?->id);

        $replacement = $publisher->saveDraft($author, $notice, $this->draftData([
            'slug' => 'new-term-learning-update',
            'title' => 'New term learning update',
            'content' => 'A replacement that is still private.',
        ]));

        $this->assertSame('draft', $replacement->workflow_state);
        $this->assertSame($firstRevision->id, $notice->fresh()->published_revision_id);
        $this->assertSame('Term learning update', $notice->fresh()->title);

        $publisher->submit($author, $notice, $replacement);
        $publisher->approve($reviewer, $notice, $replacement);
        $scheduledFor = CarbonImmutable::now(NoticePublisher::TIMEZONE)->addMinutes(10);
        $publisher->publish($reviewer, $notice, $replacement, $scheduledFor);

        $scheduled = $notice->fresh();
        $this->assertSame($firstRevision->id, $scheduled->published_revision_id);
        $this->assertSame($replacement->id, $scheduled->scheduled_revision_id);
        $this->assertSame('scheduled', $replacement->fresh()->workflow_state);
        $this->assertSame($notice->id, app(NoticeVisibility::class)->findDisplayableSlug('term-learning-update')['notice']?->id);

        $publisher->releaseDue($scheduledFor->addSecond());

        $released = $notice->fresh();
        $this->assertSame($replacement->id, $released->published_revision_id);
        $this->assertSame('new-term-learning-update', $released->slug);
        $this->assertDatabaseHas('notice_slugs', [
            'notice_id' => $released->id,
            'slug' => 'term-learning-update',
        ]);
        $former = app(NoticeVisibility::class)->findDisplayableSlug('term-learning-update', $scheduledFor->addSecond());
        $this->assertSame($released->id, $former['notice']?->id);
        $this->assertSame($released->id, $former['redirect']?->id);
    }

    public function test_expiry_withdrawal_archiving_and_restore_follow_the_lifecycle(): void
    {
        $this->grantEditorialPermissions();
        $now = CarbonImmutable::parse('2026-11-10 08:00:00', NoticePublisher::TIMEZONE);
        CarbonImmutable::setTestNow($now);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(NoticePublisher::class);
        $visibility = app(NoticeVisibility::class);

        $notice = $publisher->createDraft($author, $this->draftData([
            'expires_at' => $now->addHours(2)->format('Y-m-d H:i:s'),
        ]));
        $revision = $notice->revisions->sole();
        $publisher->submit($author, $notice, $revision);
        $publisher->approve($reviewer, $notice, $revision);
        $publisher->publish($reviewer, $notice, $revision);

        $this->assertTrue($visibility->isDisplayable($notice->fresh(), $now->addHour()));
        $this->assertFalse($visibility->isDisplayable($notice->fresh(), $now->addHours(2)));

        $publisher->withdraw($reviewer, $notice);
        $this->assertSame('withdrawn', $notice->fresh()->publication_state);
        $this->assertFalse($visibility->isDisplayable($notice->fresh(), $now->addHour()));

        $publisher->archive($reviewer, $notice);
        $this->assertSame('archived', $notice->fresh()->publication_state);
        $this->assertNotNull($notice->fresh()->archived_at);

        $publisher->restore($reviewer, $notice);
        $restored = $notice->fresh();
        $this->assertSame('draft', $restored->publication_state);
        $this->assertNull($restored->published_revision_id);
        $this->assertFalse($visibility->isDisplayable($restored, $now->addHour()));
    }

    public function test_only_one_active_urgent_notice_can_be_published(): void
    {
        $this->grantEditorialPermissions();
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(NoticePublisher::class);

        $first = $this->approvedNotice($publisher, $author, $reviewer, $this->draftData([
            'slug' => 'urgent-weather-update',
            'title' => 'Urgent weather update',
            'is_urgent' => true,
        ]));
        $publisher->publish($reviewer, $first['notice'], $first['revision']);
        $this->assertDatabaseHas('notices', [
            'id' => $first['notice']->id,
            'publication_state' => 'published',
            'is_urgent' => true,
            'audience' => 'public',
        ]);

        $second = $this->approvedNotice($publisher, $author, $reviewer, $this->draftData([
            'slug' => 'urgent-transport-update',
            'title' => 'Urgent transport update',
            'is_urgent' => true,
        ]));

        try {
            $publisher->publish($reviewer, $second['notice'], $second['revision']);
            $this->fail('A second active urgent Notice must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Only one active urgent', json_encode($exception->errors()));
        }

        $this->assertSame('published', $first['notice']->fresh()->publication_state);
        $this->assertSame('approved', $second['revision']->fresh()->workflow_state);
        $this->assertSame($first['notice']->id, app(NoticeVisibility::class)->urgent()?->id);
    }

    public function test_scheduled_urgent_notice_is_rechecked_before_it_becomes_public(): void
    {
        $this->grantEditorialPermissions();
        $now = CarbonImmutable::parse('2026-11-10 08:00:00', NoticePublisher::TIMEZONE);
        CarbonImmutable::setTestNow($now);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(NoticePublisher::class);

        $scheduled = $this->approvedNotice($publisher, $author, $reviewer, $this->draftData([
            'slug' => 'scheduled-urgent-update',
            'title' => 'Scheduled urgent update',
            'is_urgent' => true,
        ]));
        $scheduledFor = $now->addHour();
        $publisher->publish($reviewer, $scheduled['notice'], $scheduled['revision'], $scheduledFor);

        $live = $this->approvedNotice($publisher, $author, $reviewer, $this->draftData([
            'slug' => 'live-urgent-update',
            'title' => 'Live urgent update',
            'is_urgent' => true,
        ]));
        $publisher->publish($reviewer, $live['notice'], $live['revision']);
        $this->assertDatabaseHas('notices', [
            'id' => $live['notice']->id,
            'publication_state' => 'published',
            'is_urgent' => true,
        ]);

        $publisher->releaseDue($scheduledFor->addSecond());

        $this->assertSame('approved', $scheduled['revision']->fresh()->workflow_state);
        $this->assertNull($scheduled['notice']->fresh()->scheduled_revision_id);
        $this->assertSame('draft', $scheduled['notice']->fresh()->publication_state);
        $this->assertSame($live['notice']->id, app(NoticeVisibility::class)->urgent($scheduledFor->addSecond())?->id);
    }

    private function grantEditorialPermissions(): void
    {
        config(['permissions.roles.teacher' => ['notices.author', 'notices.review', 'notices.publish']]);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'teacher']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function draftData(array $overrides = []): array
    {
        return array_replace([
            'slug' => 'term-learning-update',
            'title' => 'Term learning update',
            'content' => "## A fresh term\n\nStudents are learning together.",
            'audience' => 'public',
            'date' => '2026-11-10',
            'priority' => null,
            'is_urgent' => false,
            'publication_start_at' => null,
            'expires_at' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{notice: Notice, revision: NoticeRevision}
     */
    private function approvedNotice(NoticePublisher $publisher, User $author, User $reviewer, array $data): array
    {
        $notice = $publisher->createDraft($author, $data);
        $revision = $notice->revisions->sole();
        $publisher->submit($author, $notice, $revision);
        $publisher->approve($reviewer, $notice, $revision);

        return ['notice' => $notice, 'revision' => $revision];
    }
}
