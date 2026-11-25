<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PublicNavigation;
use App\Support\SiteIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFrontendFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_identity_profile_only_contains_approved_fields(): void
    {
        Setting::set('school_name', 'Pragyan Pathashala Test Campus');
        Setting::set('tagline', 'Learning with care');
        Setting::set('logo_url', 'https://example.test/crest.svg');
        Setting::set('address', 'Birendranagar, Surkhet');
        Setting::set('phone', '+977 83 520100');
        Setting::set('email', 'office@example.test');
        Setting::set('website', 'https://example.test');
        Setting::set('public_location', 'Birendranagar, Surkhet');
        Setting::set('public_directions_url', 'https://maps.example.test/directions');
        Setting::set('public_office_hours', 'Sunday to Friday, 9 AM to 4 PM');
        Setting::set('public_visit_instructions', 'Please arrange visits with the school office first.');
        Setting::set('internal_secret', 'never expose this');

        $profile = SiteIdentity::publicProfile();

        $this->assertSame([
            'name',
            'tagline',
            'logo_url',
            'address',
            'phone',
            'email',
            'website',
            'website_placeholder',
            'location',
            'directions_url',
            'office_hours',
            'visit_instructions',
            'brand',
        ], array_keys($profile));
        $this->assertSame('Pragyan Pathashala Test Campus', $profile['name']);
        $this->assertSame('Learning with care', $profile['tagline']);
        $this->assertSame('Birendranagar, Surkhet', $profile['location']);
        $this->assertSame('https://maps.example.test/directions', $profile['directions_url']);
        $this->assertSame('Sunday to Friday, 9 AM to 4 PM', $profile['office_hours']);
        $this->assertSame('Please arrange visits with the school office first.', $profile['visit_instructions']);
        $this->assertSame('#19518C', $profile['brand']['primary']);
        $this->assertArrayNotHasKey('internal_secret', $profile);
        $this->assertSame('Pragyan Pathashala Test Campus', SiteIdentity::name());
        $this->assertSame('+977 83 520100', SiteIdentity::contact()['phone']);
    }

    public function test_public_identity_uses_safe_fallbacks_when_optional_settings_are_missing(): void
    {
        $profile = SiteIdentity::publicProfile();

        $this->assertSame('Pragyan Pathashala', $profile['name']);
        $this->assertSame('Learn. Grow. Succeed.', $profile['tagline']);
        $this->assertSame('', $profile['logo_url']);
        $this->assertSame('', $profile['website_placeholder']);
        $this->assertSame('', $profile['phone']);
        $this->assertSame('', $profile['email']);
        $this->assertSame('', $profile['directions_url']);
        $this->assertSame('', $profile['office_hours']);
        $this->assertSame('', $profile['visit_instructions']);
    }

    public function test_public_shell_uses_the_identity_projection_and_phase_one_navigation(): void
    {
        Setting::set('school_name', 'Pragyan Pathashala Test Campus');
        Setting::set('internal_secret', 'never expose this');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Pragyan Pathashala Test Campus')
            ->assertSee('data-home-text-hero', false)
            ->assertSee('Learn. Grow. Succeed.')
            ->assertSee('name="theme-color" content="#19518C"', false)
            ->assertSee('href="#main-content"', false)
            ->assertSee('<main id="main-content" tabindex="-1"', false)
            ->assertSee('data-public-header', false)
            ->assertSee('data-nav-group-trigger', false)
            ->assertSee('aria-controls="public-nav-school-life"', false)
            ->assertDontSee('aria-controls="public-nav-discover-pragyan"', false)
            ->assertSee(route('login'), false)
            ->assertDontSee('internal_secret')
            ->assertDontSee('never expose this')
            ->assertDontSee('href="'.route('public.about').'#facilities"', false)
            ->assertDontSee('href="'.route('public.academics').'#grade-stages"', false)
            ->assertDontSee('href="'.route('public.admissions').'#fees"', false)
            ->assertDontSee('href="'.route('public.news').'"', false)
            ->assertDontSee('href="'.route('public.gallery').'"', false);
    }

    public function test_public_identity_is_used_on_pages_that_previously_read_the_application_name(): void
    {
        Setting::set('school_name', 'Configured Pragyan Name');

        foreach (['public.team', 'public.news', 'public.gallery'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('Configured Pragyan Name');
        }
    }

    public function test_all_existing_public_routes_continue_to_render(): void
    {
        foreach ([
            'home',
            'public.about',
            'public.team',
            'public.academics',
            'public.admissions',
            'public.notices',
            'public.calendar',
            'public.news',
            'public.gallery',
            'public.contact',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_navigation_registry_exposes_only_phase_one_destinations(): void
    {
        $routes = collect(PublicNavigation::groups())
            ->flatMap(fn (array $group) => PublicNavigation::links($group))
            ->pluck('route')
            ->all();

        $this->assertSame([
            'home',
            'public.notices',
            'public.calendar',
            'public.admissions',
            'public.contact',
        ], $routes);
        $this->assertNotContains('public.news', $routes);
        $this->assertNotContains('public.gallery', $routes);
        $this->assertSame($routes, collect(PublicNavigation::footerGroups())
            ->flatMap(fn (array $group) => $group['links'])
            ->pluck('route')
            ->all());
    }
}
