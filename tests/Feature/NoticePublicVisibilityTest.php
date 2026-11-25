<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\NoticeRevision;
use App\Models\NoticeSlug;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Notices\NoticePublisher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoticePublicVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_legacy_public_audiences_remain_visible_but_private_and_draft_notices_do_not(): void
    {
        $this->legacyNotice(['title' => 'Published public notice', 'slug' => 'published-public-notice', 'audience' => 'public']);
        $this->legacyNotice(['title' => 'Published all notice', 'slug' => 'published-all-notice']);
        $this->legacyNotice(['title' => 'Draft public notice', 'slug' => 'draft-public-notice', 'status' => 'draft', 'audience' => 'public']);
        $this->legacyNotice(['title' => 'Parents-only notice', 'slug' => null, 'audience' => 'parents']);

        $this->get(route('public.notices'))
            ->assertOk()
            ->assertSee('Published public notice')
            ->assertSee('Published all notice')
            ->assertDontSee('Draft public notice')
            ->assertDontSee('Parents-only notice');

        $this->get(route('public.notices.show', 'published-public-notice'))
            ->assertOk()
            ->assertSee('Published public notice');
        $this->get(route('public.notices.show', 'draft-public-notice'))->assertNotFound();
    }

    public function test_public_detail_strips_raw_html_and_former_public_slugs_redirect_safely(): void
    {
        [$notice, $revision] = $this->publishedNotice([
            'slug' => 'safe-family-update',
            'title' => 'Safe family update',
            'content' => "# Safe update\n\n<script>alert('do not execute')</script>\n\n[Unsafe](javascript:alert('x'))",
        ]);
        NoticeSlug::create([
            'notice_id' => $notice->id,
            'slug' => 'previous-safe-family-update',
        ]);

        $this->get(route('public.notices.show', $notice->slug))
            ->assertOk()
            ->assertSee('Safe update')
            ->assertDontSee('<script>', false)
            ->assertDontSee('javascript:', false);

        $this->get(route('public.notices.show', 'previous-safe-family-update'))
            ->assertRedirect(route('public.notices.show', $notice->slug))
            ->assertStatus(301);

        $notice->update(['publication_state' => 'withdrawn', 'status' => 'draft', 'withdrawn_at' => now()]);
        $this->get(route('public.notices.show', 'previous-safe-family-update'))->assertNotFound();

        $this->assertSame('published', $revision->workflow_state);
    }

    public function test_listing_paginates_and_the_archive_route_excludes_nonpublic_records(): void
    {
        foreach (range(1, 13) as $number) {
            $this->legacyNotice([
                'title' => 'Public notice '.$number,
                'slug' => 'public-notice-'.$number,
                'date' => sprintf('2026-11-%02d', $number),
            ]);
        }
        $this->legacyNotice(['title' => 'Private archive record', 'slug' => null, 'audience' => 'teachers']);

        $this->get(route('public.notices'))
            ->assertOk()
            ->assertSee('Public notice 13')
            ->assertDontSee('Private archive record')
            ->assertSee('page=2', false);

        $this->get(route('public.notices', ['page' => 2]))
            ->assertOk()
            ->assertSee('Public notice 1');

        $this->get(route('public.notices.archive'))
            ->assertOk()
            ->assertSee('Published notice archive')
            ->assertDontSee('Private archive record');
    }

    public function test_homepage_uses_shared_latest_notice_and_only_renders_an_eligible_urgent_banner(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 08:00:00', NoticePublisher::TIMEZONE));
        $this->legacyNotice([
            'title' => 'Earlier family update',
            'slug' => 'earlier-family-update',
            'date' => '2026-11-09',
        ]);
        [$urgent] = $this->publishedNotice([
            'slug' => 'urgent-weather-update',
            'title' => 'Urgent weather update',
            'content' => 'School opening information for families.',
            'date' => '2026-11-10',
            'is_urgent' => true,
            'priority' => 5,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Urgent school notice')
            ->assertSee('Urgent weather update')
            ->assertSee(route('public.notices.show', $urgent->slug), false);

        $urgent->update(['expires_at' => CarbonImmutable::now(NoticePublisher::TIMEZONE)->subSecond()->utc()]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Urgent school notice')
            ->assertDontSee('Urgent weather update');
    }

    public function test_admin_can_withdraw_a_legacy_published_notice_from_the_editor(): void
    {
        $notice = $this->legacyNotice([
            'title' => 'Legacy notice awaiting review',
            'slug' => 'legacy-notice-awaiting-review',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.notices.edit', $notice))
            ->assertOk()
            ->assertSee('Withdraw notice');

        $this->actingAs($admin)
            ->patch(route('admin.notices.withdraw', $notice))
            ->assertRedirect();
        $this->assertDatabaseHas('notices', [
            'id' => $notice->id,
            'status' => 'draft',
            'publication_state' => 'withdrawn',
        ]);

        $this->get(route('public.notices'))
            ->assertOk()
            ->assertDontSee('Legacy notice awaiting review');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function legacyNotice(array $overrides = []): Notice
    {
        return Notice::create(array_replace([
            'title' => 'Legacy public notice',
            'content' => 'A preserved legacy notice.',
            'audience' => 'all',
            'posted_by' => 'School office',
            'date' => '2026-11-01',
            'status' => 'published',
            'slug' => 'legacy-public-notice-'.Notice::query()->count(),
            'publication_state' => null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Notice, 1: NoticeRevision}
     */
    private function publishedNotice(array $overrides = []): array
    {
        config(['permissions.roles.teacher' => ['notices.author', 'notices.review', 'notices.publish']]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(NoticePublisher::class);
        $notice = $publisher->createDraft($author, array_replace([
            'slug' => 'public-notice',
            'title' => 'Public notice',
            'content' => 'A safe public notice.',
            'audience' => 'public',
            'date' => '2026-11-10',
            'priority' => null,
            'is_urgent' => false,
            'publication_start_at' => null,
            'expires_at' => null,
        ], $overrides));
        $revision = $notice->revisions->sole();
        $publisher->submit($author, $notice, $revision);
        $publisher->approve($reviewer, $notice, $revision);
        $publisher->publish($reviewer, $notice, $revision);

        return [$notice->fresh(['publishedRevision']), $revision->fresh()];
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'teacher']);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'admin']);

        return $user;
    }
}
