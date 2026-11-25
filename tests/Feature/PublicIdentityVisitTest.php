<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use App\Support\SiteIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicIdentityVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_has_an_honest_empty_state_when_no_verified_details_exist(): void
    {
        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee('Verified visit and contact details are being prepared by the school office.')
            ->assertDontSee('Get directions');
    }

    public function test_contact_renders_only_the_allowlisted_verified_visit_details(): void
    {
        Setting::set('address', 'Birendranagar, Surkhet');
        Setting::set('phone', '+977 83 520100');
        Setting::set('email', 'office@example.test');
        Setting::set('public_location', 'Near the school office');
        Setting::set('public_office_hours', 'Sunday to Friday, 9 AM to 4 PM');
        Setting::set('public_directions_url', 'https://maps.example.test/directions');
        Setting::set('public_visit_instructions', 'Please arrange a visit with the school office first.');
        Setting::set('internal_secret', 'must never appear on the public website');

        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee('Birendranagar, Surkhet')
            ->assertSee('+977 83 520100')
            ->assertSee('office@example.test')
            ->assertSee('Near the school office')
            ->assertSee('Sunday to Friday, 9 AM to 4 PM')
            ->assertSee('Please arrange a visit with the school office first.')
            ->assertSee('href="https://maps.example.test/directions"', false)
            ->assertDontSee('must never appear on the public website');
    }

    public function test_invalid_or_non_http_directions_are_hidden_from_public_profile_and_contact(): void
    {
        foreach ([
            'javascript:alert(1)',
            'data:text/html,unsafe',
            'mailto:office@example.test',
            '//untrusted.example.test/path',
            'https://user:password@example.test/private',
        ] as $unsafeUrl) {
            Setting::set('public_directions_url', $unsafeUrl);

            $this->assertSame('', SiteIdentity::publicProfile()['directions_url']);
            $this->get(route('public.contact'))
                ->assertOk()
                ->assertDontSee('Get directions')
                ->assertDontSee($unsafeUrl, false);
        }
    }

    public function test_future_website_address_is_plain_text_and_suppresses_an_old_link(): void
    {
        Setting::set('website', 'https://outdated.example.test');
        Setting::set('public_website_placeholder', 'www.pragyanpathashala.com.np');

        $this->assertSame('', SiteIdentity::publicProfile()['website']);
        $this->get(route('public.contact'))
            ->assertOk()
            ->assertSee('Website coming soon')
            ->assertSee('www.pragyanpathashala.com.np')
            ->assertDontSee('href="https://outdated.example.test"', false)
            ->assertDontSee('href="www.pragyanpathashala.com.np"', false);
    }

    public function test_public_identity_editor_requires_its_own_permission_and_updates_only_the_allowlist(): void
    {
        $this->get(route('admin.public-identity.index'))->assertRedirect(route('login'));

        $unprivileged = $this->userWithRole('principal');
        $this->actingAs($unprivileged)
            ->get(route('admin.public-identity.index'))
            ->assertForbidden();

        config(['permissions.roles.teacher' => ['public_identity.manage']]);
        $editor = $this->userWithRole('teacher');
        Setting::set('internal_secret', 'keep unchanged');

        $this->actingAs($editor)
            ->get(route('admin.public-identity.index'))
            ->assertOk()
            ->assertSee('Manage only the verified contact and visit details shown to families.');

        $this->actingAs($editor)
            ->put(route('admin.public-identity.update'), [
                'address' => 'Birendranagar, Surkhet',
                'phone' => '+977 83 520100',
                'email' => 'office@example.test',
                'public_website_placeholder' => 'www.example.edu.np',
                'public_location' => 'School office',
                'public_office_hours' => 'Sunday to Friday',
                'public_directions_url' => 'https://maps.example.test/directions',
                'public_visit_instructions' => 'Please arrange a visit first.',
                'internal_secret' => 'attempted overwrite',
            ])
            ->assertRedirect(route('admin.public-identity.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Birendranagar, Surkhet', Setting::get('address'));
        $this->assertSame('School office', Setting::get('public_location'));
        $this->assertSame('www.example.edu.np', Setting::get('public_website_placeholder'));
        $this->assertSame('https://maps.example.test/directions', Setting::get('public_directions_url'));
        $this->assertSame('keep unchanged', Setting::get('internal_secret'));
        $this->assertFalse($editor->hasPermission('settings.manage'));
    }

    public function test_public_identity_editor_rejects_an_unsafe_directions_url(): void
    {
        config(['permissions.roles.teacher' => ['public_identity.manage']]);
        $editor = $this->userWithRole('teacher');

        $this->actingAs($editor)
            ->put(route('admin.public-identity.update'), [
                'public_directions_url' => 'javascript:alert(1)',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('public_directions_url');

        $this->assertNull(Setting::get('public_directions_url'));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
