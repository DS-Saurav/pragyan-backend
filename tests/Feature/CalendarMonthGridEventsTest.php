<?php

namespace Tests\Feature;

use App\Models\SchoolEvent;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Renders the public calendar view with in-memory SchoolEvent instances so the
 * month-grid event chips can be verified without touching the database. The
 * route's is_public filtering is upstream of this view and remains unchanged;
 * this test only covers the rendering path the route already feeds.
 */
class CalendarMonthGridEventsTest extends TestCase
{
    /**
     * Render the calendar view with the same data shape the route provides.
     */
    private function renderFor(string $monthDate, $events): string
    {
        $month = Carbon::parse($monthDate)->startOfMonth();

        $events = collect($events);
        $today = Carbon::parse('2026-10-26');

        return view('frontend.calendar', [
            'events' => $events,
            'days' => collect(),
            'month' => $month,
            'byDate' => $events->groupBy(fn ($event) => $event->date),
            'today' => $today,
            'upcoming' => $events->reject(fn ($event) => $event->date < $today->toDateString())->take(8),
            'prevMonth' => '/calendar?month=2026-09',
            'nextMonth' => '/calendar?month=2026-11',
        ])->render();
    }

    private function event(string $title, string $date, string $type, string $description = ''): SchoolEvent
    {
        return new SchoolEvent([
            'title' => $title,
            'date' => $date,
            'type' => $type,
            'description' => $description,
            'is_public' => true,
        ]);
    }

    public function test_october_grid_stays_35_cells_with_events_on_correct_days(): void
    {
        $events = [
            $this->event('Teachers Day Assembly', '2026-10-05', 'event'),
            $this->event('Mathematics Unit Test', '2026-10-07', 'exam'),
        ];

        $html = $this->renderFor('2026-10-01', $events);

        $this->assertSame(35, substr_count($html, 'class="cal-day'));
        // The exam chip renders inside the October 7 cell's markup, aligned by date key.
        $this->assertMatchesRegularExpression(
            '/cal-date">7<\/span>\s*<ul class="cal-events"[^>]*>\s*<li class="cal-event ev-exam"[^>]*>\s*<span class="sr-only">Examination:\s*<\/span>\s*Mathematics Unit Test\s*<\/li>/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/cal-date">5<\/span>\s*<ul class="cal-events"[^>]*>\s*<li class="cal-event ev-event"[^>]*>\s*<span class="sr-only">School event:\s*<\/span>\s*Teachers Day Assembly\s*<\/li>/s',
            $html
        );
        // No duplicate base class regression.
        $this->assertStringNotContainsString('cal-daycal-day', $html);
        // No stray Blade output characters.
        $this->assertStringNotContainsString(': >', $html);
    }

    public function test_multiple_events_on_one_day_overflow_compactly(): void
    {
        $events = [
            $this->event('First Event A', '2026-10-05', 'event'),
            $this->event('Second Event B', '2026-10-05', 'holiday'),
            $this->event('Third Event C', '2026-10-05', 'exam'),
        ];

        $html = $this->renderFor('2026-10-01', $events);

        // Only two chips are shown for the day; the rest collapse into a counter.
        $this->assertSame(1, substr_count($html, 'cal-more">+ 1 more'));
        $this->assertSame(2, substr_count($html, '<li class="cal-event'));

        // The grid still has 35 cells.
        $this->assertSame(35, substr_count($html, 'class="cal-day'));
    }

    public function test_august_grid_stays_42_cells_without_affected_dates(): void
    {
        // October events render nothing on August's grid because no date matches.
        $events = [$this->event('October Only Event', '2026-10-05', 'event')];

        $html = $this->renderFor('2026-08-01', $events);

        $this->assertSame(42, substr_count($html, 'class="cal-day'));
        // No in-grid event chip renders (no matching date); the title only remains
        // in the month-independent agenda list, which is existing behavior.
        $this->assertSame(0, substr_count($html, 'class="cal-event'));
    }

    public function test_june_grid_stays_35_cells(): void
    {
        $html = $this->renderFor('2026-06-01', collect());

        $this->assertSame(35, substr_count($html, 'class="cal-day'));
    }
}
