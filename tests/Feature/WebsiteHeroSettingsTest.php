<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\WebsiteSettingsController;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Website content & homepage hero management (public-website Phase 1).
 *
 * Public homepage authority is deliberately separate from general settings:
 * only admin has it by default. Persistence uses Setting::set(), uploads go
 * through HeroImage (GD) onto the public disk, and the homepage reflects
 * saved content — or hides sections that have no reliable content to show.
 */
class WebsiteHeroSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    // ------------------------------------------------------------ authz

    public function test_admin_can_open_the_website_content_page(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.settings.website.index'))
            ->assertOk()
            ->assertSee('Website Content');
    }

    public function test_principal_cannot_manage_public_website_content_without_an_explicit_grant(): void
    {
        $principal = $this->userWithRole('principal');

        $this->actingAs($principal)
            ->get(route('admin.settings.website.index'))
            ->assertForbidden();
        $this->actingAs($principal)
            ->put(route('admin.settings.website.update'), ['website_hero_headline' => 'Unauthorised homepage change'])
            ->assertForbidden();
        $this->actingAs($principal)
            ->post(route('admin.settings.website.images.action'), ['action' => 'remove', 'index' => 0])
            ->assertForbidden();

        $this->assertFalse($principal->can('public_homepage.manage'));
        $this->assertNull(Setting::get('website_hero_headline'));
    }

    public function test_staff_without_settings_permission_cannot_view_website_content(): void
    {
        foreach (['eca_coordinator', 'teacher'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.settings.website.index'))
                ->assertForbidden();
        }
    }

    public function test_staff_without_settings_permission_cannot_change_website_content(): void
    {
        foreach (['eca_coordinator', 'teacher'] as $role) {
            $acting = $this->actingAs($this->userWithRole($role));

            $acting->put(route('admin.settings.website.update'), ['website_hero_headline' => 'Hacked'])
                ->assertForbidden();
            $this->actingAs($this->userWithRole($role))
                ->post(route('admin.settings.website.images.upload'), [])
                ->assertForbidden();
            $this->actingAs($this->userWithRole($role))
                ->post(route('admin.settings.website.images.action'), ['action' => 'remove', 'index' => 0])
                ->assertForbidden();
        }

        $this->assertSame(null, Setting::get('website_hero_headline'));
    }

    // ------------------------------------------------------------ uploads

    public function test_garbage_upload_is_rejected(): void
    {
        Storage::fake('public');

        // A genuine .txt file passes "file" but must fail the "image" rule.
        $plain = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($plain, 'plain text, not an image');
        $file = new UploadedFile($plain, 'not-an-image.txt', 'text/plain', null, true);

        // Image errors must live ONLY in the "upload" named bag — never in the
        // default bag that the page-wide flash banner renders from, where they
        // could be mistaken for a Save details failure.
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.settings.website.images.upload'), ['hero_files' => [$file]])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('upload', ['hero_files.0'])
            ->assertSessionDoesntHaveErrors(['hero_files', 'hero_files.0'], null, 'default');

        $this->assertSame('[]', (string) Setting::get('website_hero_images', '[]'));
    }

    public function test_page_still_renders_after_a_per_file_upload_error(): void
    {
        Storage::fake('public');

        // A genuine .txt file passes "file" but must fail the "image" rule,
        // producing a wildcard key (hero_files.0) in the "upload" bag.
        $plain = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($plain, 'plain text, not an image');
        $file = new UploadedFile($plain, 'not-an-image.txt', 'text/plain', null, true);

        $acting = $this->actingAs($this->userWithRole('admin'));
        $acting->post(route('admin.settings.website.images.upload'), ['hero_files' => [$file]])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('upload', ['hero_files.0']);

        // The session carries the "errors" payload in the array shape the
        // framework persists under JSON session serialization; the framework
        // marshals it back into a ViewErrorBag on the next request. Pass the
        // REAL wildcard messages produced above — not a hand-written fixture —
        // through the exact production path (ShareErrorsFromSession reads the
        // "errors" session key).
        $stored = session()->get('errors');
        $payload = $stored instanceof ViewErrorBag
            ? array_map(
                fn ($bag) => ['format' => $bag->getFormat(), 'messages' => $bag->getMessages()],
                $stored->getBags()
            )
            : (array) $stored;

        $this->assertArrayHasKey('upload', $payload);
        $this->assertNotEmpty($payload['upload']['messages'], 'A wildcard hero_files.* message must exist.');
        $this->assertArrayNotHasKey('default', $payload, 'The default (Save-details) bag must not be touched by an upload error.');

        // Rendering through the real route must NOT crash with a 500 when the
        // wildcard messages hit {{ }} as arrays, and the per-file error must
        // appear inside the upload form region only.
        $html = $this->actingAs($this->userWithRole('admin'))
            ->withSession(['errors' => $payload])
            ->get(route('admin.settings.website.index'))
            ->assertOk()
            ->baseResponse->getContent();

        $this->assertStringContainsString('must be an image', $html);
        $this->assertStringContainsString('heroUploadErrors', $html);

        // The per-file error renders inside the hero upload form area only,
        // never in the Save-details form region.
        if (! preg_match('/<form id="websiteForm".*?<\/form>/s', $html, $saveForm)) {
            $this->fail('Save-details form not found in rendered page.');
        }
        $this->assertStringNotContainsString('must be an image', $saveForm[0]);
    }

    public function test_upload_without_selecting_a_file_is_rejected_in_upload_bag(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.settings.website.images.upload'), [])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('upload', ['hero_files'])
            ->assertSessionDoesntHaveErrors(['hero_files'], null, 'default');

        $this->assertSame('[]', (string) Setting::get('website_hero_images', '[]'));
    }

    public function test_uploading_images_does_not_touch_other_website_settings(): void
    {
        Storage::fake('public');
        Setting::set('website_hero_headline', 'Keep me');
        Setting::set('website_hero_rotation', '20');

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.settings.website.images.upload'), ['hero_files' => [
                File::image('hero.jpg', 900, 600),
            ]])->assertRedirect(route('admin.settings.website.index'));

        $this->assertCount(1, WebsiteSettingsController::heroes());
        $this->assertSame('Keep me', Setting::get('website_hero_headline'));
        $this->assertSame('20', Setting::get('website_hero_rotation'));
    }

    public function test_valid_legacy_hero_upload_is_retained_without_public_cutover(): void
    {
        Storage::fake('public');

        $file = File::image('hero.jpg', 900, 600);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.settings.website.images.upload'), ['hero_files' => [$file]])
            ->assertRedirect(route('admin.settings.website.index'));

        $heroes = WebsiteSettingsController::heroes();
        $this->assertCount(1, $heroes);
        Storage::disk('public')->assertExists($heroes[0]['path']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-home-text-hero', false)
            ->assertDontSee('hero-slide', false)
            ->assertDontSee($heroes[0]['path'], false);
    }

    public function test_hero_alts_update_and_reordering_works(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userWithRole('admin'));

        $this->post(route('admin.settings.website.images.upload'), ['hero_files' => [
            File::image('one.jpg', 900, 600),
            File::image('two.jpg', 900, 600),
        ]])->assertRedirect();

        $this->assertCount(2, WebsiteSettingsController::heroes());

        // Reorder: move the second image up.
        $orderBefore = array_column(WebsiteSettingsController::heroes(), 'path');
        $this->post(route('admin.settings.website.images.action'), ['action' => 'move-up', 'index' => 1])
            ->assertRedirect();
        $this->assertSame([$orderBefore[1], $orderBefore[0]], array_column(WebsiteSettingsController::heroes(), 'path'));

        // Alt text keyed by path survives the reorder.
        $paths = array_column(WebsiteSettingsController::heroes(), 'path');
        $this->put(route('admin.settings.website.update'), [
            'alts' => [$paths[0] => 'Front gate', $paths[1] => 'Main lawn'],
        ])->assertRedirect();

        $heroes = WebsiteSettingsController::heroes();
        $this->assertSame('Front gate', $heroes[0]['alt']);
        $this->assertSame('Main lawn', $heroes[1]['alt']);
    }

    public function test_removing_a_hero_deletes_its_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userWithRole('admin'));

        $this->post(route('admin.settings.website.images.upload'), ['hero_files' => [
            File::image('hero.jpg', 900, 600),
        ]])->assertRedirect();

        $path = WebsiteSettingsController::heroes()[0]['path'];
        Storage::disk('public')->assertExists($path);

        $this->post(route('admin.settings.website.images.action'), ['action' => 'remove', 'index' => 0])
            ->assertRedirect();

        $this->assertCount(0, WebsiteSettingsController::heroes());
        Storage::disk('public')->assertMissing($path);
    }

    // ------------------------------------------------------------ saving details (independent of images)

    public function test_save_details_with_zero_heroes_saves_settings_without_hero_validation(): void
    {
        Storage::fake('public');

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.settings.website.update'), [
                'website_hero_headline' => 'Saved without photos',
                'website_hero_intro' => 'No photograph was provided.',
                'website_hero_rotation' => '12',
                'website_home_intro' => 'An introduction saved alongside zero heroes.',
            ])->assertRedirect(route('admin.settings.website.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('Saved without photos', Setting::get('website_hero_headline'));
        $this->assertSame('No photograph was provided.', Setting::get('website_hero_intro'));
        $this->assertSame('12', Setting::get('website_hero_rotation'));
        $this->assertSame('An introduction saved alongside zero heroes.', Setting::get('website_home_intro'));
        $this->assertSame([], WebsiteSettingsController::heroes());
    }

    public function test_saving_text_settings_leaves_heroes_order_and_alts_untouched(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userWithRole('admin'));

        $this->post(route('admin.settings.website.images.upload'), ['hero_files' => [
            File::image('one.jpg', 900, 600),
            File::image('two.jpg', 900, 600),
        ]])->assertRedirect();

        // Give both heroes alt text and an explicit order.
        $paths = array_column(WebsiteSettingsController::heroes(), 'path');
        $this->put(route('admin.settings.website.update'), [
            'alts' => [$paths[0] => 'Front gate', $paths[1] => 'Main lawn'],
            'website_hero_rotation' => '20',
        ])->assertRedirect();

        $before = WebsiteSettingsController::heroes();

        // Save details WITHOUT any alt keys — must not reset alt text, order
        // or remove the photographs themselves.
        $this->put(route('admin.settings.website.update'), [
            'website_hero_headline' => 'Different headline',
            'website_hero_rotation' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($before, WebsiteSettingsController::heroes());
        $this->assertSame('Different headline', Setting::get('website_hero_headline'));
    }

    public function test_removing_all_legacy_heroes_keeps_the_homepage_text_only(): void
    {
        Storage::fake('public');
        $this->actingAs($this->userWithRole('admin'));

        $this->post(route('admin.settings.website.images.upload'), ['hero_files' => [
            File::image('only.jpg', 900, 600),
        ]])->assertRedirect();

        $this->post(route('admin.settings.website.images.action'), ['action' => 'remove', 'index' => 0])
            ->assertRedirect();
        $this->assertCount(0, WebsiteSettingsController::heroes());

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-home-text-hero', false)
            ->assertDontSee('hero-slide', false)
            ->assertDontSee('hero-fallback', false);
    }

    public function test_homepage_reflects_saved_hero_text_without_rendering_legacy_ctas_or_statistics(): void
    {
        Setting::set('website_hero_headline', 'Welcome to our school');
        Setting::set('website_hero_intro', 'A caring community for every child.');
        Setting::set('website_cta_primary_text', 'Apply today');
        Setting::set('website_cta_primary_url', '/admissions');
        Setting::set('website_stats', json_encode([
            ['value' => '2082', 'label' => 'Established'],
            ['value' => '24', 'label' => 'Classes'],
        ]));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Welcome to our school')
            ->assertSee('A caring community for every child.')
            ->assertSee(route('public.admissions').'#current-status', false)
            ->assertSee(route('public.contact').'#visit', false)
            ->assertDontSee('Apply today')
            ->assertDontSee('2082')
            ->assertDontSee('Established')
            ->assertDontSee('stats-strip');

        $this->assertSame('Apply today', Setting::get('website_cta_primary_text'));
        $this->assertSame('2082', json_decode((string) Setting::get('website_stats'), true)[0]['value']);
    }

    public function test_homepage_hides_statistics_and_principal_without_content(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('stats-strip')
            ->assertDontSee('principal-band');
    }

    public function test_homepage_never_presents_the_management_system_as_the_school(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('School Management System')
            ->assertDontSee('school management system');

        $this->get(route('home'))->assertSee('Learn. Grow. Succeed.');
    }

    // ------------------------------------------------------------ details

    public function test_cta_urls_must_be_internal_paths_or_links(): void
    {
        Setting::set('website_hero_images', json_encode([]));

        $this->actingAs($this->userWithRole('admin'))
            ->put(route('admin.settings.website.update'), [
                'website_cta_primary_text' => 'Intriguing',
                'website_cta_primary_url' => 'javascript:alert(1)',
            ])->assertSessionHasErrors('website_cta_primary_url');

        $this->assertStringNotContainsString('javascript:', (string) Setting::get('website_cta_primary_url', ''));
    }
}
