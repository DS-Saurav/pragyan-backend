<?php

namespace Tests\Feature;

use App\Models\PublicPage;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PublicPages\PublicPagePublisher;
use App\Support\PublicNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicPagesPublicVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_fixed_routes_are_honest_and_absent_from_navigation(): void
    {
        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('Information is being prepared')
            ->assertSee('href="'.route('public.contact').'#visit"', false);
        $this->get(route('public.academics'))
            ->assertOk()
            ->assertSee('Academic information is being prepared')
            ->assertSee('href="'.route('public.contact').'#visit"', false);

        $this->assertNotContains('public.about', $this->navigationRoutes());
        $this->assertNotContains('public.academics', $this->navigationRoutes());
        $this->get(route('home'))
            ->assertDontSee('href="'.route('public.about').'"', false)
            ->assertDontSee('href="'.route('public.academics').'"', false)
            ->assertDontSee('data-home-academics-preview', false);
    }

    public function test_about_navigation_appears_only_while_a_revision_is_public(): void
    {
        $page = $this->publish(PublicPage::TYPE_ABOUT, [
            'title' => 'Our approved school story',
        ]);

        $this->assertContains('public.about', $this->navigationRoutes());
        $this->assertNotContains('public.academics', $this->navigationRoutes());
        $this->get(route('home'))
            ->assertSee('href="'.route('public.about').'"', false);

        app(PublicPagePublisher::class)->withdraw($this->editor(), $page);

        $this->assertNotContains('public.about', $this->navigationRoutes());
        $this->get(route('home'))
            ->assertDontSee('href="'.route('public.about').'"', false);
    }

    public function test_academics_renders_explicit_stages_in_order_without_school_class_query_or_render(): void
    {
        SchoolClass::query()->create([
            'name' => 'INTERNAL-ONLY-CLASS-DO-NOT-PUBLISH',
            'section' => 'Z',
        ]);
        $page = $this->publish(PublicPage::TYPE_ACADEMICS, [
            'title' => 'Approved learning stages',
            'stages' => [
                [
                    'title' => 'Early Years',
                    'stage_label' => 'Foundation',
                    'description_markdown' => 'A play-led start to learning.',
                    'key_points' => ['Curiosity', 'Belonging'],
                ],
                [
                    'title' => 'Primary Learning',
                    'stage_label' => 'Primary',
                    'description_markdown' => 'A carefully sequenced programme.',
                    'key_points' => ['Literacy', 'Numeracy'],
                ],
            ],
        ]);

        $revision = $page->fresh('publishedRevision.academicStages')->publishedRevision;
        $this->assertSame(
            ['Early Years', 'Primary Learning'],
            $revision?->academicStages->pluck('title')->all(),
        );
        $this->assertSame([0, 1], $revision?->academicStages->pluck('display_order')->all());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('public.academics'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()
            ->assertSeeInOrder(['Early Years', 'Primary Learning'], false)
            ->assertDontSee('INTERNAL-ONLY-CLASS-DO-NOT-PUBLISH');
        $this->assertCount(0, collect($queries)->filter(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'school_classes'),
        ));
        $this->assertContains('public.academics', $this->navigationRoutes());
    }

    public function test_homepage_academics_preview_and_metadata_use_only_the_current_published_revision(): void
    {
        Setting::set('school_name', 'Pragyan Pathashala');
        Setting::set('tagline', 'Approved identity fallback.');
        SchoolClass::query()->create([
            'name' => 'INTERNAL-HOMEPAGE-CLASS-DO-NOT-PUBLISH',
            'section' => 'Z',
        ]);
        $page = $this->publish(PublicPage::TYPE_ACADEMICS, [
            'title' => 'Learning stages for families',
            'introduction_markdown' => 'Approved learning information chosen by the school.',
            'stages' => [
                [
                    'title' => 'Foundation Years',
                    'stage_label' => 'Early learning',
                    'description_markdown' => 'A public description of the first learning stage.',
                    'key_points' => [],
                ],
                [
                    'title' => 'Primary Learning',
                    'stage_label' => 'Primary',
                    'description_markdown' => 'A public description of the next learning stage.',
                    'key_points' => [],
                ],
                [
                    'title' => 'Secondary Learning',
                    'stage_label' => 'Secondary',
                    'description_markdown' => 'A public description of the later learning stage.',
                    'key_points' => [],
                ],
            ],
        ]);
        app(PublicPagePublisher::class)->saveDraft($this->editor(), $page, [
            'title' => 'PRIVATE REPLACEMENT MUST NOT APPEAR',
            'introduction_markdown' => 'PRIVATE INTRODUCTION MUST NOT APPEAR',
            'history_markdown' => null,
            'mission_markdown' => null,
            'vision_markdown' => null,
            'approach_values' => [],
            'facilities_markdown' => null,
            'stages' => [[
                'title' => 'PRIVATE STAGE MUST NOT APPEAR',
                'stage_label' => null,
                'description_markdown' => null,
                'key_points' => [],
            ]],
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('home'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()
            ->assertSee('data-home-academics-preview', false)
            ->assertSee('Learning journey')
            ->assertSee('Early learning to Secondary')
            ->assertSee('Learning stages for families')
            ->assertSeeInOrder(['Foundation Years', 'Primary Learning', 'Secondary Learning'], false)
            ->assertSee('content="Pragyan Pathashala — Approved learning information chosen by the school."', false)
            ->assertDontSee('PRIVATE REPLACEMENT MUST NOT APPEAR')
            ->assertDontSee('PRIVATE INTRODUCTION MUST NOT APPEAR')
            ->assertDontSee('PRIVATE STAGE MUST NOT APPEAR')
            ->assertDontSee('INTERNAL-HOMEPAGE-CLASS-DO-NOT-PUBLISH')
            ->assertDontSee('<meta property="og:image"', false);
        $this->assertCount(0, collect($queries)->filter(
            fn (array $query): bool => str_contains(strtolower($query['query']), 'school_classes'),
        ));
    }

    public function test_homepage_hides_the_academics_preview_after_withdrawal(): void
    {
        $page = $this->publish(PublicPage::TYPE_ACADEMICS, [
            'title' => 'Withdrawable Academic Preview',
            'stages' => [[
                'title' => 'Visible before withdrawal',
                'stage_label' => null,
                'description_markdown' => null,
                'key_points' => [],
            ]],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-home-academics-preview', false)
            ->assertSee('Withdrawable Academic Preview');

        app(PublicPagePublisher::class)->withdraw($this->editor(), $page);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-home-academics-preview', false)
            ->assertDontSee('Withdrawable Academic Preview');
    }

    public function test_published_page_renders_safe_markdown_not_raw_html_or_javascript_url(): void
    {
        $this->publish(PublicPage::TYPE_ABOUT, [
            'introduction_markdown' => "## Verified information\n\n<script data-phase4b-script=\"unsafe\"></script>\n\n[Unsafe](javascript:alert(1337))",
        ]);

        $this->get(route('public.about'))
            ->assertOk()
            ->assertSee('Verified information')
            ->assertDontSee('data-phase4b-script', false)
            ->assertDontSee('href="javascript:alert(1337)"', false);
    }

    /** @param array<string, mixed> $overrides */
    private function publish(string $type, array $overrides = []): PublicPage
    {
        config(['permissions.roles.teacher' => [
            'public_pages.author',
            'public_pages.review',
            'public_pages.publish',
        ]]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(PublicPagePublisher::class);
        $data = array_replace([
            'title' => 'Approved public page',
            'introduction_markdown' => 'Approved information for families.',
            'history_markdown' => null,
            'mission_markdown' => null,
            'vision_markdown' => null,
            'approach_values' => [],
            'facilities_markdown' => null,
            'stages' => [],
        ], $overrides);
        $page = $publisher->createDraft($author, $type, $data);
        $revision = $page->revisions->sole();
        $publisher->submit($author, $page, $revision);
        $publisher->approve($reviewer, $page, $revision);
        $publisher->publish($reviewer, $page, $revision);

        return $page->fresh();
    }

    /** @return array<int, string> */
    private function navigationRoutes(): array
    {
        return collect(PublicNavigation::groups())
            ->flatMap(fn (array $group) => PublicNavigation::links($group))
            ->pluck('route')
            ->all();
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'teacher']);

        return $user;
    }
}
