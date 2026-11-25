<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\PublicTeamProfile;
use App\Models\PublicTeamProfileRevision;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Media\MediaLibrary;
use App\Services\PublicTeam\PublicTeamProfilePublisher;
use App\Services\PublicTeam\PublicTeamVisibility;
use App\Support\PublicTeamProfileView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicTeamProfileSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_team_uses_a_narrow_projection_and_never_renders_legacy_staff_data(): void
    {
        $this->allowTeamEditorialWork();
        $staff = $this->activeStaff([
            'name' => 'INTERNAL STAFF NAME',
            'phone' => '98-PRIVATE-PHONE',
            'email' => 'private-staff@example.test',
            'gender' => 'private-gender',
            'basic_salary' => 81000,
            'bank_account' => 'PRIVATE-ACCOUNT-993',
            'pan_no' => 'PRIVATE-PAN-993',
            'photo' => 'staff/legacy-private-photo.jpg',
            'bio' => 'PRIVATE LEGACY BIOGRAPHY',
            'show_on_website' => true,
        ]);
        $portrait = $this->publishedPortrait();
        [, $revision] = $this->publishedProfile($staff, $portrait, [
            'display_name' => 'Public Team Name',
            'public_role' => 'Learning Guide',
            'qualification' => 'Approved Qualification',
            'bio_markdown' => 'A short public introduction for families.',
        ]);

        $view = app(PublicTeamVisibility::class)->profiles()->sole();

        $this->assertInstanceOf(PublicTeamProfileView::class, $view);
        $this->assertSame([
            'displayName',
            'publicRole',
            'teamGroup',
            'qualification',
            'bioHtml',
            'bioExcerpt',
            'displayOrder',
            'initials',
            'portraitUrl',
            'portraitAlt',
        ], array_keys(get_object_vars($view)));
        $this->assertSame('Public Team Name', $view->displayName);
        $this->assertSame(route('media.published', $portrait->id), $view->portraitUrl);
        $this->assertFalse(property_exists($view, 'staffMember'));
        $this->assertFalse(property_exists($view, 'staff_member_id'));

        $this->get(route('public.team'))
            ->assertOk()
            ->assertSee('Public Team Name')
            ->assertSee('Learning Guide')
            ->assertSee(route('media.published', $portrait->id), false)
            ->assertDontSee('INTERNAL STAFF NAME')
            ->assertDontSee('98-PRIVATE-PHONE')
            ->assertDontSee('private-staff@example.test')
            ->assertDontSee('private-gender')
            ->assertDontSee('PRIVATE-ACCOUNT-993')
            ->assertDontSee('PRIVATE-PAN-993')
            ->assertDontSee('PRIVATE LEGACY BIOGRAPHY')
            ->assertDontSee('legacy-private-photo.jpg');

        $this->assertSame($staff->id, $revision->staff_member_id);
    }

    public function test_public_profile_disappears_immediately_when_the_linked_staff_member_becomes_inactive_or_is_deleted(): void
    {
        $this->allowTeamEditorialWork();
        $staff = $this->activeStaff();
        [, $revision] = $this->publishedProfile($staff);

        $this->assertCount(1, app(PublicTeamVisibility::class)->profiles());

        $staff->update(['status' => 'inactive']);
        $this->assertTrue(app(PublicTeamVisibility::class)->profiles()->isEmpty());
        $this->get(route('public.team'))
            ->assertOk()
            ->assertSee('Our Team is being prepared')
            ->assertDontSee('Public Team Name');

        $staff->update(['status' => 'active']);
        $this->assertCount(1, app(PublicTeamVisibility::class)->profiles());

        $staff->delete();
        $this->assertNull($revision->fresh()->staff_member_id);
        $this->assertTrue(app(PublicTeamVisibility::class)->profiles()->isEmpty());
        $this->get(route('public.team'))
            ->assertOk()
            ->assertSee('Our Team is being prepared')
            ->assertDontSee('Public Team Name');
    }

    public function test_withdrawn_portrait_is_hidden_immediately_and_initials_are_shown_instead(): void
    {
        $this->allowTeamEditorialWork();
        $staff = $this->activeStaff();
        $portrait = $this->publishedPortrait();
        $this->publishedProfile($staff, $portrait, ['display_name' => 'Asha Rana']);

        $this->get(route('public.team'))
            ->assertOk()
            ->assertSee(route('media.published', $portrait->id), false)
            ->assertDontSee('AR');

        app(MediaLibrary::class)->withdrawConsent($this->editor(), $portrait);

        $this->get(route('media.published', $portrait->id))->assertNotFound();
        $this->get(route('public.team'))
            ->assertOk()
            ->assertDontSee(route('media.published', $portrait->id), false)
            ->assertSee('AR');
    }

    private function allowTeamEditorialWork(): void
    {
        config(['permissions.roles.teacher' => [
            'public_team.author',
            'public_team.review',
            'public_team.publish',
            'media.publish',
        ]]);
    }

    /** @param array<string, mixed> $overrides */
    private function activeStaff(array $overrides = []): StaffMember
    {
        return StaffMember::create(array_replace([
            'name' => 'Approved Internal Staff',
            'designation' => 'Teacher',
            'type' => 'teaching',
            'basic_salary' => 0,
            'status' => 'active',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: PublicTeamProfile, 1: PublicTeamProfileRevision}
     */
    private function publishedProfile(StaffMember $staff, ?MediaAsset $portrait = null, array $overrides = []): array
    {
        $author = $this->editor();
        $reviewer = $this->editor();
        $publisher = app(PublicTeamProfilePublisher::class);
        $profile = $publisher->createDraft($author, array_replace([
            'staff_member_id' => $staff->id,
            'display_name' => 'Public Team Name',
            'public_role' => 'Class Teacher',
            'team_group' => 'teaching',
            'qualification' => null,
            'bio_markdown' => null,
            'display_order' => 0,
        ], $overrides));
        $revision = $profile->revisions()->sole();

        if ($portrait !== null) {
            app(MediaLibrary::class)->replacePublicTeamProfileRevisionPortrait($author, $revision, $portrait, [
                'alt_text' => 'An approved public portrait',
            ]);
        }

        $publisher->submit($author, $profile, $revision);
        $publisher->approve($reviewer, $profile, $revision);
        $publisher->publish($reviewer, $profile, $revision);

        return [$profile->fresh(), $revision->fresh()];
    }

    private function publishedPortrait(): MediaAsset
    {
        $token = (string) Str::uuid();

        return MediaAsset::create([
            'disk' => 'media_originals',
            'original_path' => "originals/{$token}.jpg",
            'public_disk' => 'media_derivatives',
            'public_path' => "published/{$token}.jpg",
            'original_filename' => 'approved-portrait.jpg',
            'storage_filename' => "{$token}.jpg",
            'mime_type' => 'image/jpeg',
            'file_size' => 1,
            'width' => 1,
            'height' => 1,
            'checksum' => hash('sha256', $token),
            'title' => 'Approved public portrait',
            'default_alt_text' => 'An approved public portrait',
            'category' => 'teams',
            'status' => 'published',
            'published_at' => now(),
            'consent_state' => 'confirmed',
            'consent_confirmed_at' => now(),
        ]);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'teacher']);

        return $user;
    }
}
