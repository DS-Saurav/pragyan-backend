<?php

namespace Tests\Feature;

use App\Models\SchoolEvent;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PublicTranslations\PublicTranslationSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CalendarAccessAndVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_parent_and_student_cannot_infer_private_calendar_data(): void
    {
        $privateEvent = $this->event([
            'title' => 'Private staffing meeting',
            'description' => 'Only school staff may see this private schedule.',
            'date' => '2026-10-14',
            'is_public' => false,
        ]);

        foreach (['parent', 'student'] as $role) {
            $response = $this->actingAs($this->userWithRole($role))
                ->get(route('admin.calendar.index'));

            $response->assertForbidden()
                ->assertDontSee($privateEvent->title)
                ->assertDontSee($privateEvent->description)
                ->assertDontSee($privateEvent->date);
        }
    }

    public function test_staff_member_with_calendar_view_can_open_internal_calendar(): void
    {
        config(['permissions.roles.teacher' => ['calendar.view']]);
        $event = $this->event(['title' => 'Staff planning meeting', 'is_public' => false]);

        $this->actingAs($this->userWithRole('teacher'))
            ->get(route('admin.calendar.index'))
            ->assertOk()
            ->assertSee($event->title);
    }

    public function test_calendar_view_permission_does_not_grant_calendar_management(): void
    {
        config(['permissions.roles.teacher' => ['calendar.view']]);

        $this->actingAs($this->userWithRole('teacher'))
            ->post(route('admin.calendar.store'), [
                'title' => 'Unauthorised event',
                'date' => '2026-10-15',
                'type' => 'event',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('school_events', 0);
    }

    public function test_calendar_manager_can_manage_private_dates_but_not_public_calendar_content(): void
    {
        $manager = $this->userWithRole('eca_coordinator');
        $privateEvent = $this->event([
            'title' => 'Private planning date',
            'description' => 'Private planning information.',
            'is_public' => false,
        ]);
        $publicEvent = $this->event([
            'title' => 'Existing public event',
            'description' => 'Existing public information.',
            'is_public' => true,
        ]);

        $this->actingAs($manager)->post(route('admin.calendar.store'), [
            'title' => 'New private event',
            'date' => '2026-10-16',
            'type' => 'event',
            'description' => 'Internal only.',
        ])->assertRedirect();
        $this->assertDatabaseHas('school_events', [
            'title' => 'New private event',
            'is_public' => false,
        ]);

        $this->actingAs($manager)->post(route('admin.calendar.store'), [
            'title' => 'Attempted public event',
            'date' => '2026-10-17',
            'type' => 'event',
            'description' => 'Must not be published by a coordinator.',
            'is_public' => true,
        ])->assertForbidden();
        $this->assertDatabaseMissing('school_events', ['title' => 'Attempted public event']);

        $this->actingAs($manager)->put(route('admin.calendar.update', $privateEvent), [
            'title' => 'Updated private planning date',
            'date' => '2026-10-18',
            'type' => 'holiday',
            'description' => 'Still internal.',
        ])->assertRedirect();
        $this->assertDatabaseHas('school_events', [
            'id' => $privateEvent->id,
            'title' => 'Updated private planning date',
            'is_public' => false,
        ]);

        $this->actingAs($manager)->put(route('admin.calendar.update', $privateEvent), [
            'title' => 'Attempted public update',
            'date' => '2026-10-18',
            'type' => 'holiday',
            'description' => 'Must stay private.',
            'is_public' => true,
        ])->assertForbidden();
        $this->assertDatabaseHas('school_events', [
            'id' => $privateEvent->id,
            'title' => 'Updated private planning date',
            'is_public' => false,
        ]);

        $this->actingAs($manager)->put(route('admin.calendar.update', $publicEvent), [
            'title' => 'Attempted public withdrawal',
            'date' => '2026-10-19',
            'type' => 'event',
            'description' => 'Must not change.',
            'is_public' => false,
        ])->assertForbidden();
        $this->assertDatabaseHas('school_events', [
            'id' => $publicEvent->id,
            'title' => 'Existing public event',
            'is_public' => true,
        ]);

        $this->actingAs($manager)
            ->delete(route('admin.calendar.destroy', $publicEvent))
            ->assertForbidden();
        $this->assertModelExists($publicEvent);

        $this->actingAs($manager)
            ->get(route('admin.public-localisations.content.edit', [
                PublicTranslationSource::SCHOOL_EVENT,
                $publicEvent,
            ]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete(route('admin.calendar.destroy', $privateEvent))
            ->assertRedirect();
        $this->assertModelMissing($privateEvent);
    }

    public function test_admin_can_create_update_delete_and_translate_public_calendar_content(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('admin.calendar.store'), [
            'title' => 'Administrator public event',
            'date' => '2026-10-20',
            'type' => 'event',
            'description' => 'Approved public calendar information.',
            'is_public' => true,
        ])->assertRedirect();
        $event = SchoolEvent::query()->where('title', 'Administrator public event')->sole();
        $this->assertTrue((bool) $event->is_public);

        $this->actingAs($admin)->put(route('admin.calendar.update', $event), [
            'title' => 'Updated administrator public event',
            'date' => '2026-10-21',
            'type' => 'exam',
            'description' => 'Updated approved public calendar information.',
            'is_public' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('school_events', [
            'id' => $event->id,
            'title' => 'Updated administrator public event',
            'is_public' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.public-localisations.content.edit', [
                PublicTranslationSource::SCHOOL_EVENT,
                $event,
            ]))
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('admin.calendar.destroy', $event))
            ->assertRedirect();
        $this->assertModelMissing($event);
    }

    public function test_public_calendar_only_displays_explicitly_public_events(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 09:00:00', 'Asia/Kathmandu'));
        $publicEvent = $this->event([
            'title' => 'Public school fair',
            'description' => 'A public community event.',
            'date' => '2026-10-14',
            'is_public' => true,
        ]);
        $privateEvent = $this->event([
            'title' => 'Private faculty briefing',
            'description' => 'This must never reach the public page.',
            'date' => '2026-10-15',
            'is_public' => false,
        ]);

        $this->get(route('public.calendar', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee($publicEvent->title)
            ->assertSee($publicEvent->description)
            ->assertDontSee($privateEvent->title)
            ->assertDontSee($privateEvent->description);
    }

    public function test_invalid_month_falls_back_to_the_current_nepal_month(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-26 09:00:00', 'Asia/Kathmandu'));

        $this->get(route('public.calendar', ['month' => '2026-13']))
            ->assertOk()
            ->assertSee('October 2026');

        $this->get(route('public.calendar', ['month' => 'not-a-month']))
            ->assertOk()
            ->assertSee('October 2026');
    }

    public function test_calendar_uses_sunday_first_bounded_grid_events_and_a_separate_upcoming_limit(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 09:00:00', 'Asia/Kathmandu'));
        $gridEvent = $this->event(['title' => 'October grid event', 'date' => '2026-10-14', 'is_public' => true]);
        $historicEvent = $this->event(['title' => 'Historic public event', 'date' => '2010-10-14', 'is_public' => true]);

        foreach (range(1, 10) as $day) {
            $this->event([
                'title' => 'Upcoming event '.$day,
                'date' => sprintf('2026-11-%02d', $day),
                'is_public' => true,
            ]);
        }

        $response = $this->get(route('public.calendar', ['month' => '2026-10']));

        $response->assertOk()
            ->assertViewHas('days', function (Collection $days): bool {
                return $days->first()?->dayOfWeek === CarbonInterface::SUNDAY
                    && $days->last()?->dayOfWeek === CarbonInterface::SATURDAY;
            })
            ->assertViewHas('events', function (Collection $events) use ($gridEvent, $historicEvent): bool {
                return $events->contains($gridEvent)
                    && ! $events->contains($historicEvent);
            })
            ->assertViewHas('upcoming', function (Collection $events): bool {
                return $events->count() === 8
                    && $events->first()?->title === 'October grid event'
                    && $events->last()?->title === 'Upcoming event 7';
            })
            ->assertDontSee($historicEvent->title)
            ->assertDontSee('Upcoming event 8')
            ->assertDontSee('Upcoming event 10');
    }

    public function test_empty_month_long_titles_and_event_type_labels_render_safely(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 09:00:00', 'Asia/Kathmandu'));
        $longTitle = str_repeat('A very long calendar event title ', 12);
        $this->event(['title' => $longTitle, 'date' => '2026-10-03', 'type' => 'event', 'is_public' => true]);
        $this->event(['title' => 'Term examination', 'date' => '2026-10-04', 'type' => 'exam', 'is_public' => true]);
        $this->event(['title' => 'Festival holiday', 'date' => '2026-10-05', 'type' => 'holiday', 'is_public' => true]);

        $this->get(route('public.calendar', ['month' => '2026-10']))
            ->assertOk()
            ->assertSee($longTitle)
            ->assertSee('School event')
            ->assertSee('Examination')
            ->assertSee('Holiday');

        $this->get(route('public.calendar', ['month' => '2026-12']))
            ->assertOk()
            ->assertSee('No events published for this month.');
    }

    public function test_current_month_uses_nepal_time_at_the_utc_month_boundary(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-31 18:30:00', 'UTC'));

        $this->get(route('public.calendar'))
            ->assertOk()
            ->assertSee('February 2026');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function event(array $overrides = []): SchoolEvent
    {
        return SchoolEvent::create(array_replace([
            'title' => 'School event',
            'date' => '2026-10-14',
            'type' => 'event',
            'description' => 'Calendar information.',
            'is_public' => false,
        ], $overrides));
    }
}
