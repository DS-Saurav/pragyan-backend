<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\AdmissionCycle;
use App\Models\AdmissionCycleFaq;
use App\Models\AdmissionCycleGrade;
use App\Models\AdmissionCycleRequirement;
use App\Models\AdmissionCycleStep;
use App\Models\Notice;
use App\Models\SchoolEvent;
use App\Models\Setting;
use App\Services\PublicAdmissions\AdmissionCycleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAdmissionsContentTest extends TestCase
{
    use RefreshDatabase;

    private function publishedCycle(array $overrides = []): AdmissionCycle
    {
        return AdmissionCycle::factory()->published()->create(array_replace([
            'label' => 'Academic Year 2083',
            'public_status' => 'open',
            'public_intro' => 'Approved information for prospective families.',
            'display_starts_on' => now('Asia/Kathmandu')->subDay()->toDateString(),
            'display_ends_on' => now('Asia/Kathmandu')->addMonth()->toDateString(),
        ], $overrides));
    }

    public function test_admissions_page_falls_back_to_contact_when_no_public_cycle_exists(): void
    {
        $this->get(route('public.admissions'))
            ->assertOk()
            ->assertSee('Contact us for current admissions information')
            ->assertSee(route('public.contact').'#visit', false)
            ->assertDontSee('The admission journey');
    }

    public function test_admissions_page_renders_only_ordered_approved_cycle_content(): void
    {
        $cycle = $this->publishedCycle([
            'public_status' => 'closing_soon',
            'public_phone' => '+977 83 520101',
            'public_email' => 'admissions@example.test',
            'primary_action_label' => 'Book a school visit',
            'primary_action_url' => '/contact#visit',
            'fee_summary' => 'Tuition information is shared after an approved family consultation.',
        ]);
        AdmissionCycleGrade::create(['admission_cycle_id' => $cycle->id, 'grade' => 'Grade 6', 'sort_order' => 2]);
        AdmissionCycleGrade::create(['admission_cycle_id' => $cycle->id, 'grade' => 'Grade 3', 'sort_order' => 1]);
        AdmissionCycleStep::create(['admission_cycle_id' => $cycle->id, 'title' => 'Arrange a visit', 'body' => 'Meet the school office.', 'sort_order' => 2]);
        AdmissionCycleStep::create(['admission_cycle_id' => $cycle->id, 'title' => 'Share an enquiry', 'body' => 'Tell us the grade you are considering.', 'sort_order' => 1]);
        AdmissionCycleRequirement::create(['admission_cycle_id' => $cycle->id, 'label' => 'Birth certificate', 'detail' => 'Bring the original for verification.', 'sort_order' => 2]);
        AdmissionCycleRequirement::create(['admission_cycle_id' => $cycle->id, 'label' => 'Parent identification', 'detail' => 'Bring a valid form of identification.', 'sort_order' => 1]);
        AdmissionCycleFaq::create(['admission_cycle_id' => $cycle->id, 'question' => 'Can we visit first?', 'answer' => 'Yes. Please arrange a suitable time with the school office.', 'sort_order' => 1]);
        AdmissionCycleFaq::create(['admission_cycle_id' => $cycle->id, 'question' => 'Which grades are available?', 'answer' => 'The published eligible grades are shown above.', 'sort_order' => 2]);

        $response = $this->get(route('public.admissions'));

        $response->assertOk()
            ->assertSee('Academic Year 2083')
            ->assertSee('Closing soon')
            ->assertSee('Grade 3')
            ->assertSee('Grade 6')
            ->assertSee('Share an enquiry')
            ->assertSee('Arrange a visit')
            ->assertSee('Birth certificate')
            ->assertSee('Can we visit first?')
            ->assertSee('Last updated')
            ->assertSee('Tuition information is shared after an approved family consultation.')
            ->assertSee('Book a school visit')
            ->assertSee('id="process"', false)
            ->assertSee('id="requirements"', false)
            ->assertSee('id="fees"', false)
            ->assertSee('id="faq"', false);

        $response->assertSeeInOrder(['Parent identification', 'Birth certificate'])
            ->assertSeeInOrder(['Can we visit first?', 'Which grades are available?']);

        $this->assertSame(['Grade 3', 'Grade 6'], $cycle->fresh()->grades->pluck('grade')->all());
        $this->assertSame(['Share an enquiry', 'Arrange a visit'], $cycle->fresh()->steps->pluck('title')->all());
        $this->assertSame(['Parent identification', 'Birth certificate'], $cycle->fresh()->requirements->pluck('label')->all());
        $this->assertSame(['Can we visit first?', 'Which grades are available?'], $cycle->fresh()->faqs->pluck('question')->all());
    }

    public function test_public_admissions_pages_never_render_internal_applicant_records(): void
    {
        $this->publishedCycle();
        Admission::create([
            'reg_number' => 'REG-2083-00001',
            'first_name' => 'Private Applicant',
            'last_name' => 'Record',
            'gender' => 'female',
            'dob' => '2015-01-01',
            'class_applied' => 'Grade 4',
            'academic_year' => '2083',
            'parent_name' => 'Private Parent',
            'relation' => 'Mother',
            'mobile' => '+977 9800000000',
            'email' => 'private-parent@example.test',
            'status' => 'registered',
        ]);

        $this->get(route('public.admissions'))
            ->assertOk()
            ->assertDontSee('Private Applicant')
            ->assertDontSee('Private Parent')
            ->assertDontSee('REG-2083-00001')
            ->assertDontSee('private-parent@example.test');
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Private Applicant')
            ->assertDontSee('Private Parent')
            ->assertDontSee('REG-2083-00001');
    }

    public function test_admissions_page_uses_a_safe_fallback_for_a_malformed_stored_action_destination(): void
    {
        $this->publishedCycle([
            'primary_action_label' => 'Unsafe legacy link',
            'primary_action_url' => '//untrusted.example.test',
            'public_status' => 'unexpected',
        ]);

        $this->get(route('public.admissions'))
            ->assertOk()
            ->assertSee('Contact us')
            ->assertSee(route('public.contact').'#visit', false)
            ->assertDontSee('//untrusted.example.test', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Contact us');
    }

    public function test_homepage_family_updates_use_only_current_public_content_and_destinations(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-10 09:00:00', 'Asia/Kathmandu'));
        $cycle = $this->publishedCycle(['label' => 'Academic Year 2084']);
        Notice::create([
            'title' => 'Published public notice',
            'content' => 'Information for families.',
            'date' => '2026-07-09',
            'audience' => 'public',
            'posted_by' => 'School office',
            'status' => 'published',
        ]);
        Notice::create([
            'title' => 'Draft public notice',
            'content' => 'Do not display.',
            'date' => '2026-07-10',
            'audience' => 'public',
            'posted_by' => 'School office',
            'status' => 'draft',
        ]);
        SchoolEvent::create([
            'title' => 'Open house visit',
            'date' => '2026-08-02',
            'type' => 'event',
            'description' => 'Meet the school team.',
            'is_public' => true,
        ]);
        SchoolEvent::create([
            'title' => 'Internal staff meeting',
            'date' => '2026-07-11',
            'type' => 'event',
            'description' => 'Do not display.',
            'is_public' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Academic Year 2084')
            ->assertSee('Published public notice')
            ->assertSee('Open house visit')
            ->assertDontSee('Draft public notice')
            ->assertDontSee('Internal staff meeting')
            ->assertSee(route('public.admissions').'#current-status', false)
            ->assertSee(route('public.calendar', ['month' => '2026-08']), false)
            ->assertSee(route('public.notices'), false)
            ->assertDontSee('stats-strip');

        $this->assertSame($cycle->id, app(AdmissionCycleResolver::class)->current()?->id);
    }

    public function test_homepage_family_updates_and_contact_visit_anchor_render_safe_fallbacks(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Contact the school for current admissions information.')
            ->assertSee('No upcoming date published')
            ->assertSee('No current notice published')
            ->assertSee(route('public.contact').'#visit', false);
        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee('id="visit"', false)
            ->assertSee('Verified visit and contact details are being prepared by the school office.');
    }

    public function test_homepage_text_hero_does_not_render_legacy_hero_configuration_during_cutover(): void
    {
        Setting::set('website_hero_images', json_encode([
            ['path' => 'hero/first.jpg', 'alt' => 'First school photograph'],
            ['path' => 'hero/second.jpg', 'alt' => 'Second school photograph'],
        ]));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-home-text-hero', false)
            ->assertDontSee('storage/hero/first.jpg', false)
            ->assertDontSee('storage/hero/second.jpg', false)
            ->assertDontSee('First school photograph')
            ->assertDontSee('Second school photograph')
            ->assertDontSee('hero-slide', false)
            ->assertDontSee('hero-controls', false)
            ->assertDontSee('x-data=', false);
    }
}
