<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\SchoolEvent;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Media\HeroBackfillPlan;
use App\Services\Media\MediaLibrary;
use App\Services\Media\MediaSchema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MediaFoundationTest extends TestCase
{
    use DatabaseMigrations;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        // Dispose the isolated in-memory connection instead of rolling back unrelated
        // legacy migrations. The new media down/up methods are tested explicitly.
        $this->beforeApplicationDestroyed(function () {
            DB::purge('sqlite');
            RefreshDatabaseState::$migrated = false;
        });
    }

    protected function beforeRefreshingDatabase(): void
    {
        // Run this safety check before the migration trait can touch a database.
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('D2-A tests require isolated SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media_originals');
        Storage::fake('media_derivatives');
        Storage::fake('public');
    }

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function manager(): User
    {
        // Test-only grants to an existing role, never persisted assignments or production grants.
        config(['permissions.roles.teacher' => ['media.view', 'media.upload', 'media.review', 'media.publish']]);

        return $this->user('teacher');
    }

    private function library(): MediaLibrary
    {
        return app(MediaLibrary::class);
    }

    private function asset(?User $uploader = null): MediaAsset
    {
        return $this->library()->upload($uploader ?? $this->user(), UploadedFile::fake()->image('school.jpg', 640, 480),
            ['title' => 'School photo', 'default_alt_text' => 'Default description', 'category' => 'academics']);
    }

    private function published(?User $uploader = null): MediaAsset
    {
        $uploader ??= $this->user();
        $asset = $this->asset($uploader);
        $this->library()->submit($uploader, $asset);
        $reviewer = $this->user();
        $this->library()->approve($reviewer, $asset);

        return $this->library()->publish($reviewer, $asset);
    }

    private function event(bool $public = true): SchoolEvent
    {
        return SchoolEvent::create(['title' => 'School event', 'date' => '2026-11-01', 'type' => 'event', 'is_public' => $public]);
    }

    private function attach(MediaAsset $asset, SchoolEvent|Setting $owner, array $data = []): MediaAttachment
    {
        return $this->library()->attach($this->user(), $asset, array_replace([
            'attachable_type' => $owner instanceof Setting ? 'homepage-hero' : 'public-event',
            'attachable_id' => $owner->id, 'placement' => 'cover', 'display_order' => 0,
            'caption' => 'A caption', 'alt_text' => 'Context description', 'focal_x' => 25, 'focal_y' => 75,
        ], $data));
    }

    private function rejects(callable $action, string $contains): void
    {
        try {
            $action();
            $this->fail('Expected validation denial.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($contains, json_encode($e->errors()));
        }
    }

    public function test_schema_indexes_foreign_keys_and_archive_columns(): void
    {
        $this->assertTrue(app(MediaSchema::class)->ready());
        $indexes = collect(Schema::getIndexes('media_assets'));
        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['category']));
        foreach (['checksum', 'status', 'archived_at', 'consent_state'] as $column) {
            $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === [$column]));
        }
        $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === ['original_path'] && $i['unique']));
        $this->assertTrue(collect(Schema::getIndexes('media_attachments'))->contains(fn ($i) => $i['name'] === 'media_attachment_slot' && $i['unique']));
        foreach (['uploaded_by', 'reviewed_by', 'published_by', 'consent_confirmed_by', 'consent_withdrawn_by'] as $column) {
            $this->assertTrue($indexes->contains(fn ($i) => $i['columns'] === [$column]));
        }
        $this->assertTrue(collect(Schema::getIndexes('media_attachments'))->contains(fn ($i) => $i['columns'] === ['media_asset_id']));
        $this->assertCount(5, Schema::getForeignKeys('media_assets'));
        foreach (Schema::getForeignKeys('media_assets') as $fk) {
            $this->assertSame('set null', strtolower($fk['on_delete']));
        }
        $this->assertSame('restrict', strtolower(Schema::getForeignKeys('media_attachments')[0]['on_delete']));
        $this->assertFalse(Schema::hasColumn('media_assets', 'deleted_at'));
    }

    public function test_migrations_are_reversible_without_changing_existing_photo_columns(): void
    {
        $student = Schema::getColumnListing('students');
        $staff = Schema::getColumnListing('staff_members');
        $assets = require database_path('migrations/2026_11_01_000001_create_media_assets_table.php');
        $attachments = require database_path('migrations/2026_11_01_000002_create_media_attachments_table.php');
        $consent = require database_path('migrations/2026_11_09_152259_add_editorial_consent_metadata_to_media_assets_table.php');
        $attachments->down();
        $consent->down();
        $assets->down();
        $this->assertFalse(Schema::hasTable('media_assets'));
        $assets->up();
        $consent->up();
        $attachments->up();
        $this->assertTrue(app(MediaSchema::class)->ready());
        $this->assertSame($student, Schema::getColumnListing('students'));
        $this->assertSame($staff, Schema::getColumnListing('staff_members'));
    }

    public function test_draft_upload_keeps_original_private_and_records_verified_metadata(): void
    {
        $user = $this->manager();
        $asset = $this->asset($user);
        $this->assertSame('draft', $asset->status);
        $this->assertSame($user->id, $asset->uploader->id);
        $this->assertSame('academics', $asset->category);
        $this->assertSame('image/jpeg', $asset->mime_type);
        $this->assertSame(640, $asset->width);
        $this->assertSame(hash('sha256', Storage::disk('media_originals')->get($asset->original_path)), $asset->checksum);
        $this->assertNotSame($asset->original_filename, $asset->storage_filename);
        Storage::disk('media_originals')->assertExists($asset->original_path);
        $this->assertSame([], Storage::disk('media_derivatives')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get(route('media.published', $asset->id))->assertNotFound();
        $this->actingAs($user)->get(route('admin.media.original', $asset->id))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_different_manager_can_approve_and_publish_but_uploader_cannot_do_either(): void
    {
        $uploader = $this->manager();
        $asset = $this->asset($uploader);
        $asset = $this->library()->submit($uploader, $asset);
        $this->assertSame('review', $asset->status);
        $this->get(route('media.published', $asset->id))->assertNotFound();
        try {
            $this->library()->approve($uploader, $asset);
            $this->fail('Self approval must fail.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $reviewer = $this->manager();
        $this->library()->approve($reviewer, $asset);
        try {
            $this->library()->publish($uploader, $asset);
            $this->fail('Self publication must fail even after another reviewer approves.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $asset = $this->library()->publish($reviewer, $asset);
        $this->assertSame('published', $asset->status);
        $this->assertSame($reviewer->id, $asset->reviewer->id);
        $this->assertSame($reviewer->id, $asset->publisher->id);
        $this->get(route('media.published', $asset->id))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        Storage::disk('media_originals')->assertExists($asset->original_path);
        Storage::disk('media_derivatives')->assertExists($asset->public_path);
    }

    public function test_admin_wildcard_allows_self_review_and_publish(): void
    {
        $admin = $this->user();
        $asset = $this->asset($admin);
        $this->library()->submit($admin, $asset);
        $this->library()->approve($admin, $asset);
        $this->assertSame('published', $this->library()->publish($admin, $asset)->status);
        $this->assertArrayHasKey('media.publish', config('permissions.permissions'));
    }

    public function test_ordinary_role_with_wildcard_is_not_the_administrator_override(): void
    {
        config(['permissions.roles.teacher' => ['*']]);
        $user = $this->user('teacher');
        $asset = $this->asset($user);
        $this->library()->submit($user, $asset);
        $this->assertTrue($user->can('media.review'));
        $this->expectException(HttpException::class);
        $this->library()->approve($user, $asset);
    }

    public function test_workflow_cannot_skip_review_or_approval_or_edit_review_metadata(): void
    {
        $user = $this->manager();
        $asset = $this->asset($user);
        $this->rejects(fn () => $this->library()->publish($this->user(), $asset), 'Review');
        $this->library()->updateMetadata($user, $asset, ['title' => 'Revised']);
        $this->library()->submit($user, $asset);
        $this->rejects(fn () => $this->library()->publish($this->user(), $asset), 'approve');
        $this->rejects(fn () => $this->library()->updateMetadata($user, $asset, ['title' => 'Sneaky']), 'drafts');
        $this->assertSame('Revised', $asset->fresh()->title);
    }

    public function test_stable_aliases_and_independent_attachment_metadata_for_one_asset(): void
    {
        $asset = $this->published();
        $event = $this->event();
        $one = $this->attach($asset, $event);
        $two = $this->attach($asset, $event, ['display_order' => 1, 'caption' => 'Other caption', 'alt_text' => 'Other context', 'focal_x' => 90, 'focal_y' => 10]);
        $this->assertCount(2, $asset->attachments()->get());
        $this->assertSame($asset->id, $one->asset->id);
        $this->assertSame($event->id, $one->attachable->id);
        $this->assertSame('public-event', $event->getMorphClass());
        $this->assertSame(SchoolEvent::class, Relation::getMorphedModel('public-event'));
        $this->assertSame(Setting::class, Relation::getMorphedModel('homepage-hero'));
        $this->assertSame('25.00', $one->fresh()->focal_x);
        $this->assertSame('90.00', $two->fresh()->focal_x);
        $this->assertNotSame($one->caption, $two->caption);
        $this->assertNotSame($one->alt_text, $two->alt_text);
    }

    public function test_invalid_focal_points_categories_and_private_owner_types_are_rejected(): void
    {
        $asset = $this->published();
        $event = $this->event();
        foreach ([-1, 101, 'NaN'] as $x) {
            $this->rejects(fn () => $this->attach($asset, $event, ['focal_x' => $x]), 'focal_x');
        }
        foreach (['student', 'user', 'staff', SchoolEvent::class] as $type) {
            $this->rejects(fn () => $this->attach($asset, $event, ['attachable_type' => $type]), 'attachable_type');
        }
        $this->rejects(fn () => $this->library()->upload($this->user(), UploadedFile::fake()->image('x.jpg'), ['category' => 'unapproved']), 'category');
        $this->assertDatabaseCount('media_attachments', 0);
    }

    public function test_unique_owner_slot_prevents_duplicate_attachments(): void
    {
        $asset = $this->published();
        $event = $this->event();
        $this->attach($asset, $event);
        $this->rejects(fn () => $this->attach($asset, $event), 'placement');
        $this->assertDatabaseCount('media_attachments', 1);
    }

    public function test_foreign_key_restricts_asset_deletion_when_attached(): void
    {
        $asset = $this->published();
        $this->attach($asset, $this->event());
        $this->expectException(QueryException::class);
        DB::table('media_assets')->where('id', $asset->id)->delete();
    }

    public function test_user_deletion_preserves_media_attribution_fields_as_null_and_files(): void
    {
        $admin = $this->user();
        $asset = $this->asset($admin);
        DB::table('users')->where('id', $admin->id)->delete();
        $this->assertNull($asset->fresh()->uploaded_by);
        Storage::disk('media_originals')->assertExists($asset->original_path);
    }

    public function test_admin_cannot_unpublish_or_archive_media_used_by_published_content(): void
    {
        $asset = $this->published();
        $attachment = $this->attach($asset, $this->event());
        $admin = $this->user();
        $this->rejects(fn () => $this->library()->draft($admin, $asset), 'public-event');
        $this->rejects(fn () => $this->library()->archive($admin, $asset), 'public-event');
        $this->assertSame('published', $asset->fresh()->status);
        $this->library()->detach($admin, $attachment);
        $this->assertSame('draft', $this->library()->draft($admin, $asset)->status);
        $this->get(route('media.published', $asset->id))->assertNotFound();
    }

    public function test_homepage_alias_only_accepts_hero_setting_and_blocks_live_use(): void
    {
        $asset = $this->published();
        $other = Setting::set('school_name', 'School');
        $this->rejects(fn () => $this->attach($asset, $other), 'homepage');
        $hero = Setting::set('website_hero_images', '[]');
        $attachment = $this->attach($asset, $hero, ['placement' => 'hero']);
        $this->assertSame('homepage-hero', $attachment->attachable_type);
        $this->rejects(fn () => $this->library()->archive($this->user(), $asset), 'homepage-hero');
    }

    public function test_legacy_homepage_json_protects_matching_images_even_without_attachments(): void
    {
        $asset = $this->published();
        Storage::disk('public')->put('hero/legacy.jpg', Storage::disk('media_originals')->get($asset->original_path));
        Setting::set('website_hero_images', json_encode([['path' => 'hero/legacy.jpg', 'alt' => 'Existing alt']]));
        $this->rejects(fn () => $this->library()->draft($this->user(), $asset), 'legacy homepage');
        $this->rejects(fn () => $this->library()->archive($this->user(), $asset), 'legacy homepage');
        Storage::disk('public')->assertExists('hero/legacy.jpg');
    }

    public function test_unreferenced_published_asset_archives_and_restores_as_private_draft(): void
    {
        $asset = $this->published();
        $admin = $this->user();
        $this->library()->archive($admin, $asset);
        $this->assertNotNull($asset->fresh()->archived_at);
        Storage::disk('media_originals')->assertExists($asset->original_path);
        $this->get(route('media.published', $asset->id))->assertNotFound();
        $this->rejects(fn () => $this->attach($asset, $this->event()), 'published');
        $asset = $this->library()->restore($admin, $asset);
        $this->assertNull($asset->archived_at);
        $this->assertSame('draft', $asset->status);
        $this->assertNull($asset->reviewed_by);
        $this->get(route('media.published', $asset->id))->assertNotFound();
        $this->expectException(\LogicException::class);
        $asset->delete();
    }

    public function test_draft_assets_cannot_be_selected_and_draft_content_use_does_not_block_archive(): void
    {
        $draft = $this->asset();
        $this->rejects(fn () => $this->attach($draft, $this->event()), 'published');
        $asset = $this->published();
        $this->attach($asset, $this->event(false));
        $this->assertNotNull($this->library()->archive($this->user(), $asset)->archived_at);
    }

    public function test_unknown_or_deleted_attachment_owners_fail_closed(): void
    {
        $asset = $this->published();
        $event = $this->event(false);
        $this->attach($asset, $event);
        $event->delete();
        $this->rejects(fn () => $this->library()->archive($this->user(), $asset), 'published content');
    }

    public function test_guests_and_every_unassigned_role_cannot_access_media_interface_or_originals(): void
    {
        $asset = $this->asset();
        $this->get(route('admin.media.status'))->assertRedirect(route('login'));
        $this->get(route('admin.media.original', $asset->id))->assertRedirect(route('login'));
        foreach (array_diff(array_keys(config('permissions.role_labels')), ['admin']) as $role) {
            $user = $this->user($role);
            foreach (['media.view', 'media.upload', 'media.review', 'media.publish'] as $ability) {
                $this->assertFalse($user->can($ability));
            }
            $this->actingAs($user)->get(route('admin.media.status'))->assertForbidden();
            $this->get(route('admin.media.original', $asset->id))->assertForbidden();
        }
        $this->actingAs($this->user())->get(route('admin.media.status'))->assertOk();
    }

    public function test_view_permission_does_not_authorize_service_mutations(): void
    {
        config(['permissions.roles.teacher' => ['media.view']]);
        $viewer = $this->user('teacher');
        $this->actingAs($viewer)->get(route('admin.media.status'))->assertOk();
        $this->expectException(AuthorizationException::class);
        $this->library()->upload($viewer, UploadedFile::fake()->image('x.jpg'));
    }

    public function test_all_service_mutations_check_permission_on_direct_invocation(): void
    {
        $asset = $this->published();
        $attachment = $this->attach($asset, $this->event());
        $actor = $this->user('teacher');
        $actions = [
            fn () => $this->library()->submit($actor, $asset),
            fn () => $this->library()->approve($actor, $asset),
            fn () => $this->library()->publish($actor, $asset),
            fn () => $this->library()->draft($actor, $asset),
            fn () => $this->library()->archive($actor, $asset),
            fn () => $this->library()->restore($actor, $asset),
            fn () => $this->library()->updateMetadata($actor, $asset, []),
            fn () => $this->library()->attach($actor, $asset, []),
            fn () => $this->library()->detach($actor, $attachment),
        ];
        foreach ($actions as $action) {
            try {
                $action();
                $this->fail('Unauthorized service mutation succeeded.');
            } catch (AuthorizationException $e) {
                $this->assertSame(403, $e->status() ?? 403);
            }
        }
        $this->assertSame('published', $asset->fresh()->status);
        $this->assertDatabaseCount('media_attachments', 1);
    }

    public function test_missing_schema_returns_friendly_503_on_every_media_endpoint(): void
    {
        Schema::drop('media_attachments');
        $this->actingAs($this->user());
        foreach ([route('admin.media.status'), route('admin.media.original', 1), route('media.published', 1)] as $url) {
            $this->get($url)->assertStatus(503)->assertJson(['message' => 'The Media Library is not available yet. Please contact the school administrator.'])
                ->assertDontSee('SQL')->assertDontSee('D:')->assertDontSee('trace');
        }
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_incomplete_schema_returns_503_before_media_queries(): void
    {
        Schema::table('media_assets', fn ($table) => $table->dropColumn('description'));
        $this->actingAs($this->user())->get(route('admin.media.status'))->assertStatus(503);
        $this->get(route('admin.media.original', 1))->assertStatus(503);
        $this->get(route('media.published', 1))->assertStatus(503);
    }

    public function test_public_and_private_record_paths_cannot_be_injected_into_media_delivery(): void
    {
        $asset = $this->published();
        Storage::disk('public')->put('staff/private.jpg', 'private staff bytes');
        $asset->update(['public_path' => '../public/staff/private.jpg']);
        $this->getJson(route('media.published', $asset->id))->assertUnprocessable()->assertDontSee('private staff bytes');
        $asset->update(['original_path' => '../students/photo.jpg']);
        $this->actingAs($this->user())->getJson(route('admin.media.original', $asset->id))->assertUnprocessable();
        $this->get('/media/'.$asset->id.'/original')->assertNotFound();
    }

    public function test_invalid_upload_and_database_failure_leave_no_new_files_or_records(): void
    {
        $admin = $this->user();
        $this->rejects(fn () => $this->library()->upload($admin, UploadedFile::fake()->create('not-an-image.jpg', 20, 'image/jpeg')), 'image');
        $this->assertSame([], Storage::disk('media_originals')->allFiles());
        MediaAsset::creating(fn () => throw new \RuntimeException('Simulated record failure'));
        try {
            $this->asset($admin);
            $this->fail('Expected record failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated record failure', $e->getMessage());
        }
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('media_originals')->allFiles());
    }

    public function test_failed_original_storage_cleans_partial_output_and_creates_no_record(): void
    {
        $disk = Storage::disk('media_originals');
        Storage::shouldReceive('disk')->with('media_originals')->andReturn($disk);
        $mock = \Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('put')->once()->andReturnUsing(function ($path, $bytes) use ($disk) {
            $disk->put($path, substr($bytes, 0, 10));

            return false;
        });
        $mock->shouldReceive('delete')->once()->andReturnUsing(fn ($path) => $disk->delete($path));
        Storage::swap(tap(\Mockery::mock(FilesystemManager::class), function ($manager) use ($mock) {
            $manager->shouldReceive('disk')->with('media_originals')->andReturn($mock);
        }));
        $this->rejects(fn () => $this->asset(), 'stored');
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], $disk->allFiles());
    }

    public function test_failed_derivative_processing_leaves_review_state_and_no_public_file(): void
    {
        $admin = $this->user();
        $asset = $this->asset($admin);
        $this->library()->submit($admin, $asset);
        $this->library()->approve($admin, $asset);
        Storage::disk('media_originals')->put($asset->original_path, 'corrupt');
        $this->rejects(fn () => $this->library()->publish($admin, $asset), 'valid');
        $this->assertSame('review', $asset->fresh()->status);
        $this->assertNull($asset->fresh()->public_path);
        $this->assertSame([], Storage::disk('media_derivatives')->allFiles());
    }

    public function test_failed_derivative_storage_rolls_back_state_and_removes_partial_file(): void
    {
        $admin = $this->user();
        $asset = $this->asset($admin);
        $this->library()->submit($admin, $asset);
        $this->library()->approve($admin, $asset);
        $originals = Storage::disk('media_originals');
        $disk = Storage::disk('media_derivatives');
        $mock = \Mockery::mock($disk)->makePartial();
        $mock->shouldReceive('put')->once()->andReturnUsing(function ($path, $bytes) use ($disk) {
            $disk->put($path, substr($bytes, 0, 10));

            return false;
        });
        $mock->shouldReceive('delete')->once()->andReturnUsing(fn ($path) => $disk->delete($path));
        Storage::swap(tap(\Mockery::mock(FilesystemManager::class), function ($manager) use ($mock, $originals) {
            $manager->shouldReceive('disk')->with('media_originals')->andReturn($originals);
            $manager->shouldReceive('disk')->with('media_derivatives')->andReturn($mock);
        }));
        $this->rejects(fn () => $this->library()->publish($admin, $asset), 'stored');
        $this->assertSame('review', $asset->fresh()->status);
        $this->assertSame([], $disk->allFiles());
        $this->assertTrue($originals->exists($asset->original_path));
    }

    public function test_dry_run_preserves_json_files_order_and_alt_and_deduplicates_source_bytes(): void
    {
        $file = UploadedFile::fake()->image('old.jpg', 640, 480);
        $bytes = file_get_contents($file->getRealPath());
        Storage::disk('public')->put('hero/old.jpg', $bytes);
        $json = json_encode([['path' => 'hero/old.jpg', 'alt' => 'First'], ['path' => 'hero/old.jpg', 'alt' => 'Second']]);
        Setting::set('website_hero_images', $json);
        $plan = app(HeroBackfillPlan::class)->inspect();
        $this->assertSame($plan, app(HeroBackfillPlan::class)->inspect());
        $this->assertCount(1, $plan['assets']);
        $this->assertCount(2, $plan['attachments']);
        $this->assertSame([0, 1], array_column($plan['attachments'], 'display_order'));
        $this->assertSame(['First', 'Second'], array_column($plan['attachments'], 'alt_text'));
        $this->assertSame([], $plan['errors']);
        $this->assertSame($json, Setting::get('website_hero_images'));
        $this->assertSame($bytes, Storage::disk('public')->get('hero/old.jpg'));
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media_attachments', 0);
        $this->assertSame([], Storage::disk('media_originals')->allFiles());
        $this->artisan('media:hero-backfill')->assertSuccessful();
        $this->artisan('media:hero-backfill --apply')->assertFailed();
    }

    public function test_legacy_hero_cutover_manifest_blocks_a_live_switch_without_recorded_evidence_or_alt_text(): void
    {
        $file = UploadedFile::fake()->image('legacy-hero.jpg', 640, 480);
        Storage::disk('public')->put('hero/legacy-hero.jpg', file_get_contents($file->getRealPath()));
        Setting::set('website_hero_images', json_encode([
            ['path' => 'hero/legacy-hero.jpg', 'alt' => ''],
        ]));

        $plan = app(HeroBackfillPlan::class)->inspect();
        $asset = array_values($plan['assets'])[0];

        $this->assertFalse($plan['cutover']['ready']);
        $this->assertFalse($plan['cutover']['live_switch_allowed']);
        $this->assertTrue($plan['cutover']['source_preserved']);
        $this->assertSame($plan['source_checksum'], $plan['cutover']['manifest']['source_checksum']);
        $this->assertSame('not_recorded', $asset['owner_approval']);
        $this->assertSame('not_recorded', $asset['consent_or_licence_evidence']);
        $this->assertContains('Meaningful alt text is missing for one or more legacy hero images.', $plan['cutover']['blockers']);
        $this->assertContains('Owner approval and consent or licence evidence are not recorded for legacy hero sources.', $plan['cutover']['blockers']);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media_attachments', 0);
        Storage::disk('public')->assertExists('hero/legacy-hero.jpg');
    }

    public function test_dry_run_reports_missing_corrupt_and_unsafe_entries_without_writing(): void
    {
        Storage::disk('public')->put('hero/corrupt.jpg', 'not an image');
        Setting::set('website_hero_images', json_encode([
            ['path' => 'hero/missing.jpg'], ['path' => 'hero/corrupt.jpg'],
            ['path' => '../staff/photo.jpg'], ['path' => 'staff/photo.jpg'], ['path' => 'hero/x.jpg', 'alt' => []],
        ]));
        $report = app(HeroBackfillPlan::class)->inspect();
        $this->assertCount(5, $report['errors']);
        $this->assertSame([], $report['assets']);
        $this->assertDatabaseCount('media_assets', 0);
        Setting::set('website_hero_images', 'invalid json');
        $this->assertCount(1, app(HeroBackfillPlan::class)->inspect()['errors']);
    }

    public function test_missing_both_tables_also_blocks_service_upload_before_files_are_written(): void
    {
        Schema::drop('media_attachments');
        Schema::drop('media_assets');
        $admin = $this->user();
        $this->actingAs($admin)->get(route('admin.media.status'))->assertStatus(503);
        try {
            $this->asset($admin);
            $this->fail('Missing schema must not permit uploads.');
        } catch (HttpResponseException $e) {
            $this->assertSame(503, $e->getResponse()->getStatusCode());
        }
        $this->assertSame([], Storage::disk('media_originals')->allFiles());
    }

    public function test_upload_size_limit_is_enforced_before_any_storage_write(): void
    {
        $file = UploadedFile::fake()->image('too-large.jpg')->size(8193);
        $this->rejects(fn () => $this->library()->upload($this->user(), $file), '8192');
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertSame([], Storage::disk('media_originals')->allFiles());
    }

    public function test_readable_but_changed_original_cannot_be_published(): void
    {
        $admin = $this->user();
        $asset = $this->asset($admin);
        $this->library()->submit($admin, $asset);
        $this->library()->approve($admin, $asset);
        $different = UploadedFile::fake()->image('different.jpg', 800, 600);
        Storage::disk('media_originals')->put($asset->original_path, file_get_contents($different->getRealPath()));
        $this->rejects(fn () => $this->library()->publish($admin, $asset), 'changed after upload');
        $this->assertSame('review', $asset->fresh()->status);
        $this->assertSame([], Storage::disk('media_derivatives')->allFiles());
    }

    public function test_media_disks_are_outside_public_root_without_direct_storage_urls(): void
    {
        foreach (['media_originals', 'media_derivatives'] as $name) {
            $disk = config('filesystems.disks.'.$name);
            $this->assertFalse($disk['serve']);
            $this->assertSame('private', $disk['visibility']);
            $this->assertArrayNotHasKey('url', $disk);
            $this->assertFalse(str_starts_with($disk['root'], public_path()));
            $this->assertFalse(str_starts_with($disk['root'], storage_path('app/public')));
        }
    }

    private function slot(SchoolEvent $event, array $data = []): array
    {
        return array_replace([
            'attachable_type' => 'public-event', 'attachable_id' => $event->id,
            'placement' => 'cover', 'display_order' => 0,
        ], $data);
    }

    private function invalidCoordinates(): array
    {
        return ['', ' ', null, -1, 101, -0.01, 100.01, 'no-number', '1e2', '1e-2', ' 50 ', '+50', '-1',
            '50.555', 50.555, 50.550000000000004, '100.01', '00', '.5', '50.', true, false, [], new \stdClass,
            NAN, INF, -INF, "50\n"];
    }

    public function test_focal_creation_defaults_only_missing_coordinates_and_accepts_exact_boundaries(): void
    {
        $asset = $this->published();
        $event = $this->event();
        $actor = $this->user();
        $a = $this->library()->attach($actor, $asset, $this->slot($event));
        $this->assertSame('50.00', $a->fresh()->focal_x);
        $this->assertSame('50.00', $a->fresh()->focal_y);
        foreach ([['focal_x' => 25], ['focal_y' => 75]] as $index => $partial) {
            $b = $this->library()->attach($actor, $asset, $this->slot($event, $partial + ['display_order' => $index + 1]));
            $this->assertSame($index === 0 ? '25.00' : '50.00', $b->fresh()->focal_x);
            $this->assertSame($index === 0 ? '50.00' : '75.00', $b->fresh()->focal_y);
        }
        foreach ([[0, 100], [100, 0], [50, 50], [50.5, '50.55'], ['100.00', '0.01'], [0.29, 0.57]] as $index => [$x, $y]) {
            $attachment = $this->library()->attach($actor, $asset, $this->slot($event, [
                'display_order' => $index + 3, 'focal_x' => $x, 'focal_y' => $y,
            ]));
            $this->assertSame(number_format((float) $x, 2, '.', ''), $attachment->fresh()->focal_x);
            $this->assertSame(number_format((float) $y, 2, '.', ''), $attachment->fresh()->focal_y);
        }
    }

    public function test_focal_creation_rejects_invalid_representations_for_each_axis_without_partial_writes(): void
    {
        $asset = $this->published();
        $event = $this->event();
        $actor = $this->user();
        $files = [Storage::disk('media_originals')->allFiles(), Storage::disk('media_derivatives')->allFiles()];
        foreach (['focal_x', 'focal_y'] as $field) {
            foreach ($this->invalidCoordinates() as $value) {
                $this->rejects(fn () => $this->library()->attach($actor, $asset, $this->slot($event, [$field => $value])), $field);
                $this->assertDatabaseCount('media_attachments', 0);
                $this->assertSame($files, [Storage::disk('media_originals')->allFiles(), Storage::disk('media_derivatives')->allFiles()]);
            }
        }
    }

    public function test_focal_partial_updates_preserve_omitted_values_and_independent_contexts(): void
    {
        $asset = $this->published();
        $event = $this->event();
        $actor = $this->user();
        $a = $this->attach($asset, $event);
        $other = $this->attach($asset, $event, ['display_order' => 1, 'focal_x' => 90, 'focal_y' => 10]);
        $updated = $this->library()->updateAttachment($actor, $a, ['focal_x' => 0]);
        $this->assertSame('0.00', $updated->focal_x);
        $this->assertSame('75.00', $updated->focal_y);
        $updated = $this->library()->updateAttachment($actor, $a, ['focal_y' => '100.00']);
        $this->assertSame('0.00', $updated->focal_x);
        $this->assertSame('100.00', $updated->focal_y);
        $updated = $this->library()->updateAttachment($actor, $a, ['caption' => 'Updated caption']);
        $this->assertSame('0.00', $updated->focal_x);
        $this->assertSame('100.00', $updated->focal_y);
        $this->assertSame('Updated caption', $updated->caption);
        $updated = $this->library()->updateAttachment($actor, $a, []);
        $this->assertSame('0.00', $updated->focal_x);
        $this->assertSame('100.00', $updated->focal_y);
        $updated = $this->library()->updateAttachment($actor, $a, ['focal_x' => 50.55, 'focal_y' => '0.29']);
        $this->assertSame('50.55', $updated->focal_x);
        $this->assertSame('0.29', $updated->focal_y);
        $this->assertSame('90.00', $other->fresh()->focal_x);
        $this->assertSame('10.00', $other->fresh()->focal_y);
    }

    public function test_focal_invalid_updates_preserve_all_existing_metadata_and_files(): void
    {
        $asset = $this->published();
        $attachment = $this->attach($asset, $this->event());
        $actor = $this->user();
        $before = $attachment->fresh()->getRawOriginal();
        $files = [Storage::disk('media_originals')->allFiles(), Storage::disk('media_derivatives')->allFiles()];
        foreach (['focal_x', 'focal_y'] as $field) {
            foreach ($this->invalidCoordinates() as $value) {
                $this->rejects(fn () => $this->library()->updateAttachment($actor, $attachment, [
                    $field => $value, 'caption' => 'Must not persist', 'alt_text' => 'Must not persist',
                    'placement' => 'different', 'display_order' => 2,
                ]), $field);
                $this->assertSame($before, $attachment->fresh()->getRawOriginal());
                $this->assertDatabaseCount('media_attachments', 1);
                $this->assertSame($files, [Storage::disk('media_originals')->allFiles(), Storage::disk('media_derivatives')->allFiles()]);
            }
        }
    }

    public function test_placement_normalizes_ascii_case_and_outer_whitespace_before_storage(): void
    {
        $asset = $this->published();
        $event = $this->event();
        $actor = $this->user();
        foreach (['cover' => 'cover', 'Cover' => 'cover', 'COVER' => 'cover', ' Gallery-Image ' => 'gallery-image',
            'homepage-hero' => 'homepage-hero', '0' => '0', str_repeat('a', 60) => str_repeat('a', 60)] as $input => $expected) {
            $attachment = $this->library()->attach($actor, $asset, $this->slot($event, [
                'placement' => (string) $input, 'display_order' => MediaAttachment::count(),
            ]));
            $this->assertSame($expected, $attachment->fresh()->placement);
        }
    }

    public function test_placement_rejects_invalid_creation_and_updates_without_altering_existing_data(): void
    {
        $asset = $this->published();
        $event = $this->event();
        $attachment = $this->attach($asset, $event);
        $actor = $this->user();
        $before = $attachment->fresh()->getRawOriginal();
        foreach (['', '  ', 'cover_image', 'cover image', 'फोटो', 'école', '-cover', 'cover-', 'gallery--image',
            str_repeat('a', 61), 'cover!', "\0cover", true, null, [], new \stdClass] as $value) {
            $this->rejects(fn () => $this->library()->attach($actor, $asset, $this->slot($event, [
                'placement' => $value, 'display_order' => 1,
            ])), 'placement');
            $this->rejects(fn () => $this->library()->updateAttachment($actor, $attachment, [
                'placement' => $value, 'caption' => 'Rejected caption', 'focal_x' => 100,
            ]), 'placement');
            $this->assertSame($before, $attachment->fresh()->getRawOriginal());
            $this->assertDatabaseCount('media_attachments', 1);
        }
    }

    public function test_canonical_slot_duplicate_is_rejected_by_service_across_different_assets(): void
    {
        $asset = $this->published();
        $secondAsset = $this->published();
        $event = $this->event();
        $actor = $this->user();
        $attachment = $this->library()->attach($actor, $asset, $this->slot($event, ['placement' => 'Cover']));
        $this->assertSame('cover', $attachment->fresh()->placement);
        MediaAttachment::creating(fn () => $this->fail('Duplicate reached persistence instead of service validation.'));
        $this->rejects(fn () => $this->library()->attach($actor, $secondAsset, $this->slot($event, ['placement' => 'cover'])), 'already occupied');
        $this->assertDatabaseCount('media_attachments', 1);
    }

    public function test_canonical_slot_allows_other_placements_orders_and_owners(): void
    {
        $asset = $this->published();
        $event = $this->event();
        $one = $this->attach($asset, $event, ['placement' => 'Cover']);
        $two = $this->attach($asset, $event, ['placement' => ' Gallery-Image ']);
        $three = $this->attach($asset, $event, ['placement' => 'cover', 'display_order' => 1]);
        $four = $this->attach($asset, $this->event(), ['placement' => 'cover']);
        $this->assertSame('cover', $one->placement);
        $this->assertSame('gallery-image', $two->placement);
        $this->assertSame('cover', $three->placement);
        $this->assertSame('cover', $four->placement);
        $this->assertDatabaseCount('media_attachments', 4);
    }

    public function test_placement_updates_are_canonical_and_exclude_the_attachment_itself_from_duplicate_checks(): void
    {
        $asset = $this->published();
        $a = $this->attach($asset, $this->event());
        $actor = $this->user();
        $updated = $this->library()->updateAttachment($actor, $a, ['placement' => ' COVER ']);
        $this->assertSame('cover', $updated->placement);
        $updated = $this->library()->updateAttachment($actor, $a, ['placement' => ' Gallery-Image ', 'display_order' => 1]);
        $this->assertSame('gallery-image', $updated->placement);
        $this->assertSame(1, $updated->display_order);
        $this->assertSame('25.00', $updated->focal_x);
        $this->assertSame('75.00', $updated->focal_y);
    }

    public function test_canonical_slot_duplicate_update_preserves_the_original_attachment(): void
    {
        $asset = $this->published();
        $event = $this->event();
        $this->attach($asset, $event);
        $other = $this->attach($asset, $event, ['placement' => 'gallery-image']);
        $actor = $this->user();
        $before = $other->fresh()->getRawOriginal();
        $this->rejects(fn () => $this->library()->updateAttachment($actor, $other, [
            'placement' => ' COVER ', 'caption' => 'Must not persist', 'focal_x' => 50,
        ]), 'already occupied');
        $this->assertSame($before, $other->fresh()->getRawOriginal());
        $this->assertDatabaseCount('media_attachments', 2);
    }

    public function test_attachment_update_requires_manage_and_cannot_reparent_the_attachment(): void
    {
        $asset = $this->published();
        $attachment = $this->attach($asset, $this->event());
        $before = $attachment->fresh()->getRawOriginal();
        try {
            $this->library()->updateAttachment($this->user('teacher'), $attachment, ['focal_x' => 100]);
            $this->fail('Unauthorized update succeeded.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $attachment->fresh()->getRawOriginal());
        }
        $updated = $this->library()->updateAttachment($this->user(), $attachment, [
            'media_asset_id' => 999, 'attachable_type' => 'student', 'attachable_id' => 999, 'alt_text' => 'Allowed change',
        ]);
        $this->assertSame($attachment->media_asset_id, $updated->media_asset_id);
        $this->assertSame($attachment->attachable_type, $updated->attachable_type);
        $this->assertSame($attachment->attachable_id, $updated->attachable_id);
        $this->assertSame('Allowed change', $updated->alt_text);
    }

    public function test_database_unique_slot_constraint_remains_the_final_integrity_guard(): void
    {
        $asset = $this->published();
        $attachment = $this->attach($asset, $this->event());
        $this->expectException(QueryException::class);
        $asset->attachments()->create($attachment->only([
            'attachable_type', 'attachable_id', 'placement', 'display_order',
        ]));
    }
}
