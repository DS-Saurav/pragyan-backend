<?php

namespace Tests\Feature;

use App\Models\NewsStory;
use App\Models\PublicSiteTranslation;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PublicTranslations\PublicTranslationPublisher;
use App\Services\PublicTranslations\PublicTranslationSource;
use App\Support\PublicSitemap;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_and_publish_own_nepali_content_translation(): void
    {
        $admin = $this->editor('admin');
        $story = NewsStory::factory()->published()->create(['slug' => 'admin-translation']);
        $publisher = app(PublicTranslationPublisher::class);
        $translation = $publisher->saveContentDraft($admin, PublicTranslationSource::NEWS_STORY, $story, [
            'title' => 'विद्यालयको समाचार',
            'summary' => 'विद्यालयको स्वीकृत समाचार सारांश।',
            'body_markdown' => 'विद्यालयको स्वीकृत समाचार विवरण।',
            'category' => null,
            'public_attribution' => null,
        ]);
        $revision = $translation->revisions()->sole();

        $publisher->submitContent($admin, $translation, $revision);
        $publisher->approveContent($admin, $translation, $revision);
        $this->assertSame($admin->id, $revision->fresh()->reviewed_by);
        $this->assertDatabaseHas('public_translation_audit_events', [
            'translation_type' => 'content',
            'translation_id' => $translation->id,
            'action' => 'approved',
            'actor_id' => $admin->id,
        ]);

        $publisher->publishContent($admin, $translation, $revision);
        $this->assertSame('published', $translation->fresh()->publication_state);
    }

    public function test_admin_can_approve_and_publish_own_nepali_site_translation(): void
    {
        $admin = $this->editor('admin');
        $publisher = app(PublicTranslationPublisher::class);
        $translation = $publisher->saveSiteDraft($admin, PublicSiteTranslation::SCOPE_IDENTITY, [
            'name' => 'प्रज्ञा पाठशाला',
        ]);
        $revision = $translation->revisions()->sole();

        $publisher->submitSite($admin, $translation, $revision);
        $publisher->approveSite($admin, $translation, $revision);
        $this->assertSame($admin->id, $revision->fresh()->reviewed_by);
        $this->assertDatabaseHas('public_translation_audit_events', [
            'translation_type' => 'site',
            'translation_id' => $translation->id,
            'action' => 'approved',
            'actor_id' => $admin->id,
        ]);

        $publisher->publishSite($admin, $translation, $revision);
        $this->assertSame('published', $translation->fresh()->publication_state);
    }

    public function test_principal_cannot_edit_the_nepali_homepage_without_public_homepage_authority(): void
    {
        $principal = $this->editor('principal');

        $this->actingAs($principal)
            ->get(route('admin.public-localisations.site.edit', PublicSiteTranslation::SCOPE_HOMEPAGE))
            ->assertForbidden();
        $this->actingAs($principal)
            ->put(route('admin.public-localisations.site.save', PublicSiteTranslation::SCOPE_HOMEPAGE), [
                'payload' => ['hero_headline' => 'Unauthorised homepage translation'],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('public_site_translations', 0);
    }

    public function test_english_urls_stay_canonical_and_a_missing_nepali_story_never_falls_back_to_english(): void
    {
        $story = NewsStory::factory()->published()->create(['slug' => 'english-source-story']);
        $story->publishedRevision->update([
            'title' => 'English source headline that must not leak',
            'summary' => 'English source summary that must not leak.',
            'body_markdown' => 'English source body that must not leak.',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertHeaderMissing('Set-Cookie');

        $this->get('/ne/news/english-source-story')
            ->assertOk()
            ->assertSee('<html lang="ne">', false)
            ->assertSee('नेपाली अनुवाद उपलब्ध छैन')
            ->assertSee(route('public.news.show', $story->slug), false)
            ->assertDontSee('English source headline that must not leak')
            ->assertDontSee('English source summary that must not leak')
            ->assertDontSee('English source body that must not leak')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_an_explicit_language_choice_sets_only_the_locale_cookie_and_rejects_external_targets(): void
    {
        $this->get('/language/ne?to=/ne/news')
            ->assertRedirect('/ne/news')
            ->assertCookie('pp_locale', 'ne');

        $this->get('/language/ne?to=https://untrusted.example/redirect')
            ->assertRedirect('/')
            ->assertCookie('pp_locale', 'ne');
    }

    public function test_nepali_news_requires_a_separate_reviewer_then_schedules_publishes_and_withdraws_without_changing_english(): void
    {
        config(['permissions.roles.teacher' => ['news.author', 'news.review', 'news.publish']]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $story = NewsStory::factory()->published()->create(['slug' => 'school-learning-day']);
        $story->publishedRevision->update([
            'title' => 'English learning day',
            'summary' => 'English learning day summary.',
            'body_markdown' => 'English learning day article.',
        ]);

        $publisher = app(PublicTranslationPublisher::class);
        $translation = $publisher->saveContentDraft($author, PublicTranslationSource::NEWS_STORY, $story, [
            'title' => 'विद्यालय सिकाइ दिवस',
            'summary' => 'विद्यार्थीहरूको सिकाइ र सहकार्यको दिन।',
            'body_markdown' => "## सिकाइको दिन\n\nविद्यार्थीहरूले सँगै सिके।",
            'category' => 'विद्यालय जीवन',
            'public_attribution' => 'प्रज्ञान पाठशाला',
        ]);
        $draft = $translation->revisions()->firstOrFail();
        $publisher->submitContent($author, $translation, $draft);

        try {
            $publisher->approveContent($author, $translation, $draft->fresh());
            $this->fail('The translator must not approve their own Nepali revision.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('different authorized reviewer', json_encode($exception->errors()));
        }

        $approved = $publisher->approveContent($reviewer, $translation, $draft->fresh());
        $releaseAt = CarbonImmutable::now(PublicTranslationPublisher::TIMEZONE)->addMinutes(5);
        $publisher->publishContent($reviewer, $translation, $approved, $releaseAt);
        $this->assertSame('scheduled', $translation->fresh()->publication_state);
        $this->assertDatabaseHas('public_translation_audit_events', [
            'translation_type' => 'content',
            'translation_id' => $translation->id,
            'action' => 'scheduled',
            'actor_id' => $reviewer->id,
        ]);

        CarbonImmutable::setTestNow($releaseAt->addSecond());
        $publisher->releaseDue();
        $published = $translation->fresh();
        $this->assertSame('published', $published->publication_state);
        $this->assertNotNull($published->published_revision_id);
        $this->assertSame('English learning day', $story->fresh()->publishedRevision->title);
        $this->assertDatabaseHas('public_translation_audit_events', [
            'translation_type' => 'content',
            'translation_id' => $translation->id,
            'action' => 'released',
        ]);

        $this->get(route('public.ne.news.show', $story->slug))
            ->assertOk()
            ->assertSee('विद्यालय सिकाइ दिवस')
            ->assertSee('विद्यार्थीहरूले सँगै सिके।')
            ->assertDontSee('English learning day');

        $this->get(route('public.news.show', $story->slug))
            ->assertOk()
            ->assertSee('hreflang="ne" href="'.PublicSitemap::canonicalUrl('/ne/news/'.$story->slug).'"', false);
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.PublicSitemap::canonicalUrl('/ne/news/'.$story->slug).'</loc>', false)
            ->assertSee('xhtml:link rel="alternate" hreflang="ne"', false);

        $publisher->withdrawContent($reviewer, $published);
        $this->assertDatabaseHas('public_translation_audit_events', [
            'translation_type' => 'content',
            'translation_id' => $translation->id,
            'action' => 'withdrawn',
            'actor_id' => $reviewer->id,
        ]);
        $this->get(route('public.ne.news.show', $story->slug))
            ->assertOk()
            ->assertSee('नेपाली अनुवाद उपलब्ध छैन')
            ->assertDontSee('विद्यालय सिकाइ दिवस');

        $publisher->archiveContent($reviewer, $published->fresh());
        $this->assertSame('archived', $published->fresh()->publication_state);
        $publisher->restoreContent($reviewer, $published->fresh());
        $this->assertSame('draft', $published->fresh()->publication_state);
        CarbonImmutable::setTestNow();
    }

    public function test_a_changed_english_revision_hides_a_previously_published_nepali_revision_until_it_is_refreshed(): void
    {
        config(['permissions.roles.teacher' => ['news.author', 'news.review', 'news.publish']]);
        $author = $this->editor();
        $reviewer = $this->editor();
        $story = NewsStory::factory()->published()->create(['slug' => 'source-freshness']);
        $publisher = app(PublicTranslationPublisher::class);
        $translation = $publisher->saveContentDraft($author, PublicTranslationSource::NEWS_STORY, $story, [
            'title' => 'ताजा स्रोत',
            'summary' => 'स्वीकृत नेपाली सारांश।',
            'body_markdown' => 'स्वीकृत नेपाली सामग्री।',
            'category' => null,
            'public_attribution' => null,
        ]);
        $draft = $translation->revisions()->firstOrFail();
        $publisher->submitContent($author, $translation, $draft);
        $approved = $publisher->approveContent($reviewer, $translation, $draft->fresh());
        $publisher->publishContent($reviewer, $translation, $approved);

        $this->get(route('public.ne.news.show', $story->slug))
            ->assertOk()
            ->assertSee('ताजा स्रोत');

        $story->publishedRevision->update(['summary' => 'Updated English source summary.']);

        $this->get(route('public.ne.news.show', $story->slug))
            ->assertOk()
            ->assertSee('नेपाली अनुवाद उपलब्ध छैन')
            ->assertDontSee('ताजा स्रोत');
    }

    private function editor(string $role = 'teacher'): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
