<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumRevision;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_staff_without_gallery_capabilities_cannot_open_gallery_administration(): void
    {
        $this->get(route('admin.gallery.index'))->assertRedirect(route('login'));

        $this->actingAs($this->userWithPermissions([]))
            ->get(route('admin.gallery.index'))
            ->assertForbidden();

    }

    public function test_gallery_author_can_create_a_private_album_but_cannot_review_or_publish_it(): void
    {
        $author = $this->userWithPermissions(['gallery.author']);

        $this->actingAs($author)
            ->get(route('admin.gallery.create'))
            ->assertOk();

        $this->actingAs($author)
            ->post(route('admin.gallery.store'), $this->albumPayload())
            ->assertRedirect();

        $album = GalleryAlbum::query()->sole();
        $revision = $album->revisions()->sole();

        $this->assertSame('draft', $album->publication_state);
        $this->assertSame('draft', $revision->workflow_state);

        $this->actingAs($author)
            ->patch(route('admin.gallery.approve', [$album, $revision]))
            ->assertForbidden();

        $this->actingAs($author)
            ->patch(route('admin.gallery.publish', [$album, $revision]))
            ->assertForbidden();
    }

    public function test_reviewer_and_publisher_can_inspect_gallery_but_do_not_gain_author_access(): void
    {
        foreach (['gallery.review', 'gallery.publish'] as $permission) {
            $user = $this->userWithPermissions([$permission]);

            $this->actingAs($user)
                ->get(route('admin.gallery.index'))
                ->assertOk();

            $this->actingAs($user)
                ->get(route('admin.gallery.create'))
                ->assertForbidden();
        }

    }

    public function test_gallery_preview_and_unrelated_internal_records_remain_protected(): void
    {
        $album = GalleryAlbum::factory()->create();
        $revision = GalleryAlbumRevision::factory()->create(['gallery_album_id' => $album->id]);
        $author = $this->userWithPermissions(['gallery.author']);
        $unprivileged = $this->userWithPermissions([], 'librarian');

        $this->get(route('admin.gallery.preview', [$album, $revision]))
            ->assertRedirect(route('login'));

        $this->actingAs($unprivileged)
            ->get(route('admin.gallery.preview', [$album, $revision]))
            ->assertForbidden();

        $this->actingAs($author)
            ->get(route('admin.gallery.preview', [$album, $revision]))
            ->assertOk();

        $this->actingAs($author)
            ->get(route('admin.admissions.index'))
            ->assertForbidden();

        $this->actingAs($author)
            ->get(route('admin.media.original', 1))
            ->assertForbidden();

    }

    /** @return array<string, string> */
    private function albumPayload(): array
    {
        return [
            'title' => 'A learning celebration',
            'slug' => 'a-learning-celebration',
            'summary' => 'A concise public description of this school moment.',
            'album_date' => '2026-10-28',
            'category' => 'Learning',
        ];

    }

    /** @param array<int, string> $permissions */
    private function userWithPermissions(array $permissions, string $role = 'teacher'): User
    {
        config()->set('permissions.roles.'.$role, $permissions);
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
