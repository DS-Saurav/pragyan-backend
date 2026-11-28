<?php

namespace Tests\Feature;

use App\Models\GalleryAlbum;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GalleryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_staff_without_gallery_capabilities_cannot_open_gallery_administration(): void
    {
        $this->getJson('/api/admin/gallery')->assertUnauthorized();

        Sanctum::actingAs($this->userWithPermissions([]));
        $this->getJson('/api/admin/gallery')->assertForbidden();
    }

    public function test_gallery_author_can_create_album_but_cannot_feature_or_delete_it(): void
    {
        $author = $this->userWithPermissions(['gallery.author']);
        Sanctum::actingAs($author);

        $this->postJson('/api/admin/gallery', $this->albumPayload())
            ->assertCreated();

        $album = GalleryAlbum::query()->sole();

        $this->patchJson("/api/admin/gallery/{$album->id}/feature")
            ->assertForbidden();

        $this->deleteJson("/api/admin/gallery/{$album->id}")
            ->assertForbidden();
    }

    public function test_reviewer_and_publisher_can_inspect_gallery_but_do_not_gain_author_access(): void
    {
        foreach (['gallery.review', 'gallery.publish'] as $permission) {
            $user = $this->userWithPermissions([$permission]);
            Sanctum::actingAs($user);

            $this->getJson('/api/admin/gallery')->assertOk();

            $this->postJson('/api/admin/gallery', $this->albumPayload())
                ->assertForbidden();
        }
    }

    /** @return array<string, mixed> */
    private function albumPayload(): array
    {
        return [
            'title' => 'A learning celebration',
            'slug' => 'a-learning-celebration',
            'description' => 'A concise public description of this school moment.',
            'event_date' => '2026-10-28',
            'is_active' => true,
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
