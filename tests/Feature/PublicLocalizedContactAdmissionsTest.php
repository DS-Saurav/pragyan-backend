<?php

namespace Tests\Feature;

use App\Models\AdmissionCycle;
use App\Models\PublicPage;
use App\Models\PublicSiteTranslation;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PublicPages\PublicPagePublisher;
use App\Services\PublicTranslations\PublicTranslationPublisher;
use App\Services\PublicTranslations\PublicTranslationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLocalizedContactAdmissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_nepali_contact_shows_translated_visit_details_and_shared_contact_without_linking_unregistered_domain(): void
    {
        Setting::set('school_name', 'Pragyan Pathashala');
        Setting::set('address', 'English address');
        Setting::set('phone', '083-590928');
        Setting::set('email', 'pathashalap@gmail.com');
        Setting::set('website', 'https://old.example');
        Setting::set('public_website_placeholder', 'www.pragyanpathashala.com.np');
        Setting::set('public_directions_url', 'https://www.google.com/maps/search/?api=1&query=Pragyan+Pathashala');

        $admin = $this->admin();
        $publisher = app(PublicTranslationPublisher::class);
        $translation = $publisher->saveSiteDraft($admin, PublicSiteTranslation::SCOPE_IDENTITY, [
            'name' => 'प्रज्ञा पाठशाला',
            'tagline' => 'प्रविधि र परम्पराबाट सशक्त सिकाइ',
            'address' => 'वीरेन्द्रनगर–९, सुर्खेत',
            'location' => 'प्रज्ञा पाठशाला ब्लक बी',
            'office_hours' => 'बिहान १० बजेदेखि दिउँसो ४ बजेसम्म',
            'visit_instructions' => 'भ्रमणका लागि पहिले कार्यालयमा फोन गर्नुहोस्।',
        ]);
        $revision = $translation->revisions()->firstOrFail();
        $publisher->submitSite($admin, $translation, $revision);
        $publisher->approveSite($admin, $translation, $revision);
        $publisher->publishSite($admin, $translation, $revision);

        $this->get('/ne/contact')
            ->assertOk()
            ->assertSee('वीरेन्द्रनगर–९, सुर्खेत')
            ->assertSee('प्रज्ञा पाठशाला ब्लक बी')
            ->assertSee('बिहान १० बजेदेखि दिउँसो ४ बजेसम्म')
            ->assertSee('भ्रमणका लागि पहिले कार्यालयमा फोन गर्नुहोस्।')
            ->assertSee('083-590928')
            ->assertSee('pathashalap@gmail.com')
            ->assertSee('www.pragyanpathashala.com.np')
            ->assertSee('नक्सामा हेर्नुहोस्')
            ->assertDontSee('href="https://old.example"', false)
            ->assertDontSee('English address');
    }

    public function test_closed_nepali_admissions_shows_office_contact_without_application_or_fee_details(): void
    {
        $cycle = AdmissionCycle::factory()->published()->create([
            'label' => 'Academic Year 2083',
            'public_status' => 'closed',
            'public_intro' => 'English admissions introduction',
            'display_starts_on' => null,
            'display_ends_on' => null,
            'public_phone' => '083-590928',
            'fee_summary' => null,
        ]);
        $admin = $this->admin();
        $publisher = app(PublicTranslationPublisher::class);
        $translation = $publisher->saveContentDraft($admin, PublicTranslationSource::ADMISSION_CYCLE, $cycle, [
            'label' => 'शैक्षिक वर्ष २०८३',
            'public_status_message' => 'हाल भर्ना बन्द छ।',
            'public_intro' => 'नयाँ भर्नाको जानकारी विद्यालयले पछि सार्वजनिक गर्नेछ।',
        ]);
        $revision = $translation->revisions()->firstOrFail();
        $publisher->submitContent($admin, $translation, $revision);
        $publisher->approveContent($admin, $translation, $revision);
        $publisher->publishContent($admin, $translation, $revision);

        $this->get('/ne/admissions')
            ->assertOk()
            ->assertSee('हाल भर्ना बन्द छ।')
            ->assertSee('नयाँ भर्नाको जानकारी विद्यालयले पछि सार्वजनिक गर्नेछ।')
            ->assertSee('083-590928')
            ->assertSee('tel:083590928', false)
            ->assertSee(route('public.ne.contact').'#visit', false)
            ->assertDontSee('English admissions introduction')
            ->assertDontSee('Approved public fee summary');
    }

    public function test_nepali_academics_shows_both_translated_stage_label_and_title(): void
    {
        $admin = $this->admin();
        $pages = app(PublicPagePublisher::class);
        $page = $pages->createDraft($admin, PublicPage::TYPE_ACADEMICS, [
            'title' => 'Academics',
            'introduction_markdown' => 'English academic introduction.',
            'stages' => [[
                'title' => 'Grades 4–8',
                'stage_label' => 'Middle grades',
                'description_markdown' => 'English stage detail.',
                'key_points' => ['Computer Science starts in Grade 4.'],
            ]],
        ]);
        $revision = $page->revisions()->firstOrFail();
        $pages->submit($admin, $page, $revision);
        $pages->approve($admin, $page, $revision);
        $pages->publish($admin, $page, $revision);
        $page->refresh();
        $stage = $page->publishedRevision->academicStages()->firstOrFail();

        $publisher = app(PublicTranslationPublisher::class);
        $translation = $publisher->saveContentDraft($admin, PublicTranslationSource::PUBLIC_PAGE, $page, [
            'title' => 'शैक्षिक कार्यक्रम',
            'introduction_markdown' => 'स्वीकृत शैक्षिक जानकारी।',
            'stages' => [$stage->id => [
                'title' => 'कक्षा ४–८',
                'stage_label' => 'मध्यम कक्षाहरू',
                'description_markdown' => 'स्वीकृत कक्षा विवरण।',
                'key_points' => ['कक्षा ४ देखि कम्प्युटर विज्ञान।'],
            ]],
        ]);
        $localized = $translation->revisions()->firstOrFail();
        $publisher->submitContent($admin, $translation, $localized);
        $publisher->approveContent($admin, $translation, $localized);
        $publisher->publishContent($admin, $translation, $localized);

        $this->get('/ne/academics')
            ->assertOk()
            ->assertSee('मध्यम कक्षाहरू')
            ->assertSee('कक्षा ४–८')
            ->assertSee('कक्षा ४ देखि कम्प्युटर विज्ञान।')
            ->assertDontSee('English stage detail.');
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        UserRole::create(['user_id' => $user->id, 'role' => 'admin']);

        return $user;
    }
}
