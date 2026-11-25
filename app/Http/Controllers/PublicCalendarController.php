<?php

namespace App\Http\Controllers;

use App\Models\SchoolEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PublicCalendarController extends Controller
{
    public const TIMEZONE = 'Asia/Kathmandu';

    private const UPCOMING_LIMIT = 8;

    public function index(Request $request): View
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $month = $this->selectedMonth($request->query('month'), $today);
        $start = $month->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY);

        $events = SchoolEvent::query()
            ->where('is_public', true)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $monthEvents = $events->filter(function (SchoolEvent $event) use ($month): bool {
            return CarbonImmutable::parse($event->date, self::TIMEZONE)->isSameMonth($month);
        })->values();

        return view('frontend.calendar', [
            'events' => $events,
            'monthEvents' => $monthEvents,
            'days' => $this->calendarDays($start, $end),
            'month' => $month,
            'byDate' => $events->groupBy(fn (SchoolEvent $event): string => (string) $event->date),
            'today' => $today,
            'upcoming' => SchoolEvent::query()
                ->where('is_public', true)
                ->whereDate('date', '>=', $today->toDateString())
                ->orderBy('date')
                ->orderBy('id')
                ->limit(self::UPCOMING_LIMIT)
                ->get(),
            'prevMonth' => route('public.calendar', ['month' => $month->subMonth()->format('Y-m')]),
            'nextMonth' => route('public.calendar', ['month' => $month->addMonth()->format('Y-m')]),
        ]);
    }

    private function selectedMonth(mixed $monthQuery, CarbonImmutable $today): CarbonImmutable
    {
        if (! is_string($monthQuery)
            || preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $monthQuery) !== 1) {
            return $today->startOfMonth();
        }

        $month = CarbonImmutable::createFromFormat('!Y-m', $monthQuery, self::TIMEZONE);
        if ($month === false || $month->format('Y-m') !== $monthQuery) {
            return $today->startOfMonth();
        }

        return $month->startOfMonth();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function calendarDays(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $days = collect();

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $days->push($day);
        }

        return $days;
    }
}
