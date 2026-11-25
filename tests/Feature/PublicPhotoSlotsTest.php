<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Media\MediaUsage;
use App\Services\Media\MediaLibrary;
use App\Services\PublicPhotos\PublicPhotoSlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicPhotoSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media_derivatives');
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function publishedAsset(User $actor): MediaAsset
    {
        $id = (string) Str::uuid();
        $publicPath = 'published/'.$id.'.jpg';
        Storage::disk('media_derivatives')->put($publicPath, 'safe derivative');

        return MediaAsset::create([
            'disk' => 'media_originals',
            'original_path' => 'originals/'.$id.'.jpg',
            'public_disk' => 'media_derivatives',
            'public_path' => $publicPath,
            'original_filename' => 'campus.jpg',
            'storage_filename' => $id.'.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 15,
            'width' => 1200,
            'height' => 800,
            'checksum' => hash('sha256', 'safe derivative'),
            'default_alt_text' => 'Aerial view of the school campus',
            'uploaded_by' => $actor->id,
            'status' => 'published',
            'consent_state' => 'confirmed',
            'consent_confirmed_by' => $actor->id,
            'consent_confirmed_at' => now(),
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
    }

    public function test_admin_can_select_a_published_photo_with_private_rights_evidence_and_localized_alt(): void
    {
        $admin = $this->actor();
        $asset = $this->publishedAsset($admin);
        $photos = app(PublicPhotoSlots::class);

        $photos->save($admin, 'home_hero', [
            'media_asset_id' => $asset->id,
            'alt_text' => 'Aerial view of Pragyan Pathashala campus',
            'alt_text_ne' => 'प्रज्ञा पाठशाला परिसरको आकाशबाट दृश्य',
            'caption' => 'Our campus in Birendranagar',
            'focal_x' => 45,
            'focal_y' => 55,
            'source_rights_note' => 'School owner confirmed permission for public use, including visible people.',
        ]);

        $this->assertSame('Aerial view of Pragyan Pathashala campus', $photos->resolve('home_hero')['alt']);
        $this->assertSame('प्रज्ञा पाठशाला परिसरको आकाशबाट दृश्य', $photos->resolve('home_hero', 'ne')['alt']);
        $this->assertSame(['public photo slot: home_hero'], app(MediaUsage::class)->publishedUses($asset));
        $this->assertDatabaseHas('public_photo_slot_events', [
            'action' => 'selected', 'actor_id' => $admin->id, 'media_asset_id' => $asset->id,
        ]);

        $this->get('/')->assertDontSee('School owner confirmed permission for public use');
        $this->get('/admissions')->assertDontSee('School owner confirmed permission for public use');
        $this->get('/contact')->assertDontSee('School owner confirmed permission for public use');
        $this->get('/ne/admissions')->assertDontSee('School owner confirmed permission for public use');
        $this->get('/ne/contact')->assertDontSee('School owner confirmed permission for public use');
        $this->actingAs($admin)->get(route('admin.public-photos.index'))
            ->assertOk()->assertSee('School owner confirmed permission for public use');
    }

    public function test_nepali_photo_is_hidden_until_placement_has_nepali_alt_and_withdrawn_consent_hides_both(): void
    {
        $admin = $this->actor();
        $asset = $this->publishedAsset($admin);
        $photos = app(PublicPhotoSlots::class);
        $photos->save($admin, 'contact_visit', [
            'media_asset_id' => $asset->id,
            'alt_text' => 'School campus from the road',
            'source_rights_note' => 'Owner confirmed school photo may be used publicly.',
        ]);

        $this->assertNotNull($photos->resolve('contact_visit'));
        $this->assertNull($photos->resolve('contact_visit', 'ne'));

        app(MediaLibrary::class)->withdrawConsent($admin, $asset);
        $this->assertNull($photos->resolve('contact_visit'));
        $this->assertNull($photos->resolve('contact_visit', 'ne'));
    }

    public function test_unapproved_photo_and_missing_rights_note_are_rejected_and_non_admin_cannot_assign(): void
    {
        $admin = $this->actor();
        $asset = $this->publishedAsset($admin);
        $photos = app(PublicPhotoSlots::class);

        try {
            $photos->save($admin, 'home_hero', ['media_asset_id' => $asset->id, 'alt_text' => 'School campus']);
            $this->fail('Missing private rights evidence should be rejected.');
        } catch (ValidationException) {
            $this->assertNull($photos->resolve('home_hero'));
        }

        config(['permissions.roles.teacher' => ['public_homepage.manage']]);
        $teacher = $this->actor('teacher');
        $this->get(route('admin.public-photos.index'))->assertRedirect(route('login'));
        $this->actingAs($teacher)->get(route('admin.public-photos.index'))->assertForbidden();
        $this->actingAs($teacher)->put(route('admin.public-photos.update', 'home_hero'), [
            'media_asset_id' => $asset->id,
            'alt_text' => 'School campus',
            'source_rights_note' => 'School owner permission.',
        ])->assertForbidden();
    }

    public function test_live_slot_protects_asset_until_cleared(): void
    {
        $admin = $this->actor();
        $asset = $this->publishedAsset($admin);
        $photos = app(PublicPhotoSlots::class);
        $photos->save($admin, 'admissions_hero', [
            'media_asset_id' => $asset->id,
            'alt_text' => 'Campus building at Pragyan Pathashala',
            'source_rights_note' => 'Owner confirmed school photo may be used publicly.',
        ]);

        $this->expectException(ValidationException::class);
        app(MediaUsage::class)->requireUnusedByPublishedContent($asset);
    }

    public function test_additive_migration_rolls_back_in_dependency_order_and_missing_table_has_safe_fallback(): void
    {
        $migration = require database_path('migrations/2026_11_24_000001_create_public_photo_slots_tables.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('public_photo_slot_events'));
        $this->assertFalse(Schema::hasTable('public_photo_slots'));
        $this->assertNull(app(PublicPhotoSlots::class)->resolve('home_hero'));
        $this->get('/')->assertOk();

        $migration->up();
        $this->assertSame(4, \App\Models\PublicPhotoSlot::query()->count());
        $this->assertNull(app(PublicPhotoSlots::class)->resolve('home_hero'));
    }
}
