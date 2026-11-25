<?php

namespace Tests\Feature;

use App\Models\AdmissionCycle;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAdmissionsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(?string $role = null): User
    {
        $user = User::factory()->create();

        if ($role !== null) {
            UserRole::create(['user_id' => $user->id, 'role' => $role]);
        }

        return $user;
    }

    /** @return array<string, mixed> */
    private function cyclePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'label' => 'Academic Year 2083',
            'public_status' => 'open',
            'public_intro' => 'Approved public admissions information.',
            'display_starts_on' => '2026-04-01',
            'display_ends_on' => '2026-06-30',
            'public_phone' => '+977 83 520101',
            'public_email' => 'admissions@example.test',
            'primary_action_label' => 'Arrange a visit',
            'primary_action_url' => '/contact#visit',
            'fee_summary' => 'Fees are discussed during an approved family consultation.',
            'grades' => [
                ['grade' => 'Grade 2', 'sort_order' => 2],
                ['grade' => 'Grade 1', 'sort_order' => 1],
            ],
            'steps' => [
                ['title' => 'Meet the school', 'body' => 'Arrange a suitable visit time.', 'sort_order' => 1],
            ],
            'requirements' => [
                ['label' => 'Birth certificate', 'detail' => 'Bring the original for verification.', 'sort_order' => 1],
            ],
            'faqs' => [
                ['question' => 'Can we visit first?', 'answer' => 'Yes, please contact the school office.', 'sort_order' => 1],
            ],
        ], $overrides);
    }

    public function test_guests_are_redirected_from_public_admissions_management(): void
    {
        $this->get(route('admin.public-admissions.index'))
            ->assertRedirect(route('login'));
    }

    public function test_existing_staff_roles_without_the_dedicated_permission_are_forbidden(): void
    {
        foreach (['principal', 'academic_coordinator', 'teacher', 'eca_coordinator'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('admin.public-admissions.index'))
                ->assertForbidden();
        }
    }

    public function test_internal_admissions_permission_does_not_grant_public_admissions_content_access(): void
    {
        $coordinator = $this->userWithRole('academic_coordinator');

        $this->actingAs($coordinator)
            ->get(route('admin.admissions.index'))
            ->assertOk();

        $this->actingAs($coordinator)
            ->get(route('admin.public-admissions.index'))
            ->assertForbidden();
    }

    public function test_explicit_public_admissions_permission_does_not_grant_internal_admissions_access(): void
    {
        config()->set('permissions.roles.teacher', ['public_admissions.manage']);
        $editor = $this->userWithRole('teacher');

        $this->actingAs($editor)
            ->get(route('admin.public-admissions.index'))
            ->assertOk()
            ->assertSee('Public admissions');

        $this->actingAs($editor)
            ->get(route('admin.admissions.index'))
            ->assertForbidden();
    }

    public function test_controller_authorization_remains_when_route_permission_middleware_is_bypassed(): void
    {
        $editor = $this->userWithRole('teacher');

        $this->withoutMiddleware(Authorize::class)
            ->actingAs($editor)
            ->get(route('admin.public-admissions.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_publish_withdraw_and_archive_a_public_cycle(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.public-admissions.store'), $this->cyclePayload())
            ->assertRedirect();

        $cycle = AdmissionCycle::query()->sole();
        $this->assertSame('draft', $cycle->publication_state);
        $this->assertSame($admin->id, $cycle->created_by);
        $this->assertSame(['Grade 1', 'Grade 2'], $cycle->grades->pluck('grade')->all());
        $this->assertSame('Meet the school', $cycle->steps->sole()->title);
        $this->assertSame('Birth certificate', $cycle->requirements->sole()->label);
        $this->assertSame('Can we visit first?', $cycle->faqs->sole()->question);

        $this->actingAs($admin)
            ->from(route('admin.public-admissions.edit', $cycle))
            ->patch(route('admin.public-admissions.publish', $cycle))
            ->assertRedirect(route('admin.public-admissions.edit', $cycle));

        $cycle->refresh();
        $this->assertSame('published', $cycle->publication_state);
        $this->assertSame($admin->id, $cycle->published_by);
        $this->assertNotNull($cycle->published_at);

        $this->actingAs($admin)
            ->from(route('admin.public-admissions.edit', $cycle))
            ->patch(route('admin.public-admissions.withdraw', $cycle))
            ->assertRedirect(route('admin.public-admissions.edit', $cycle));
        $this->assertSame('withdrawn', $cycle->fresh()->publication_state);

        $this->actingAs($admin)
            ->from(route('admin.public-admissions.edit', $cycle))
            ->patch(route('admin.public-admissions.archive', $cycle))
            ->assertRedirect(route('admin.public-admissions.edit', $cycle));
        $this->assertSame('archived', $cycle->fresh()->publication_state);
    }

    public function test_unauthorized_mutation_is_forbidden_and_leaves_a_cycle_unchanged(): void
    {
        $cycle = AdmissionCycle::factory()->create(['publication_state' => 'draft']);
        $coordinator = $this->userWithRole('academic_coordinator');

        $this->actingAs($coordinator)
            ->patch(route('admin.public-admissions.publish', $cycle))
            ->assertForbidden();

        $this->assertSame('draft', $cycle->fresh()->publication_state);
    }

    public function test_protocol_relative_primary_action_destinations_are_rejected(): void
    {
        $admin = $this->userWithRole('admin');

        foreach (['//untrusted.example.test', '/\\untrusted.example.test'] as $destination) {
            $this->actingAs($admin)
                ->post(route('admin.public-admissions.store'), $this->cyclePayload([
                    'primary_action_url' => $destination,
                ]))
                ->assertRedirect()
                ->assertSessionHasErrors('primary_action_url');
        }

        $this->assertDatabaseCount('admission_cycles', 0);
    }

    public function test_admin_can_save_a_cycle_with_only_an_explicit_display_end_date(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.public-admissions.store'), $this->cyclePayload([
                'display_starts_on' => null,
                'display_ends_on' => '2026-06-30',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cycle = AdmissionCycle::query()->sole();
        $this->assertNull($cycle->display_starts_on);
        $this->assertSame('2026-06-30', $cycle->display_ends_on?->toDateString());
    }

    public function test_terminal_and_invalid_lifecycle_transitions_are_rejected(): void
    {
        $archived = AdmissionCycle::factory()->create(['publication_state' => 'archived']);
        $draft = AdmissionCycle::factory()->create(['publication_state' => 'draft']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.public-admissions.edit', $archived))
            ->patch(route('admin.public-admissions.publish', $archived))
            ->assertRedirect(route('admin.public-admissions.edit', $archived))
            ->assertSessionHasErrors('publication_state');
        $this->assertSame('archived', $archived->fresh()->publication_state);

        $this->actingAs($admin)
            ->from(route('admin.public-admissions.edit', $draft))
            ->patch(route('admin.public-admissions.withdraw', $draft))
            ->assertRedirect(route('admin.public-admissions.edit', $draft))
            ->assertSessionHasErrors('publication_state');
        $this->assertSame('draft', $draft->fresh()->publication_state);
    }

    public function test_publish_rejects_an_overlapping_display_window_and_preserves_draft_state(): void
    {
        AdmissionCycle::factory()->published()->create([
            'display_starts_on' => '2026-04-01',
            'display_ends_on' => '2026-06-30',
        ]);
        $candidate = AdmissionCycle::factory()->create([
            'publication_state' => 'draft',
            'display_starts_on' => '2026-06-30',
            'display_ends_on' => '2026-08-01',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->from(route('admin.public-admissions.edit', $candidate))
            ->patch(route('admin.public-admissions.publish', $candidate))
            ->assertRedirect(route('admin.public-admissions.edit', $candidate))
            ->assertSessionHasErrors('display_starts_on');

        $this->assertSame('draft', $candidate->fresh()->publication_state);
    }
}
