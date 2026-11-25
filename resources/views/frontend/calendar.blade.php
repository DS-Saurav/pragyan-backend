@extends('layouts.public', ['title' => 'Calendar', 'metaDescription' => 'The school calendar at a glance — published holidays, school events and exam dates.'])

@section('content')
    @php
        $monthEvents = $monthEvents ?? $events->filter(
            fn ($event) => \Illuminate\Support\Carbon::parse($event->date, 'Asia/Kathmandu')->isSameMonth($month),
        )->values();
        $gridDays = $days ?? collect();

        if ($gridDays->isEmpty()) {
            $gridStart = $month->copy()->startOfMonth()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
            $gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SATURDAY);
            $gridDays = collect();

            for ($gridDay = $gridStart; $gridDay->lessThanOrEqualTo($gridEnd); $gridDay = $gridDay->addDay()) {
                $gridDays->push($gridDay->copy());
            }
        }

        $typeLabel = fn (string $type): string => match ($type) {
            'exam' => 'Examination',
            'holiday' => 'Holiday',
            default => 'School event',
        };
    @endphp

    <section class="page-hero" aria-labelledby="pageTitle">
        <div class="container flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="hero-index">Academic calendar</span>
                <span class="eyebrow">Dates that matter</span>
                <h1 id="pageTitle">Every important date, at a glance</h1>
                <p>The published school calendar — holidays, events and examination dates — in one place.</p>
            </div>
            <a href="{{ route('home') }}" class="btn btn-ghost btn-sm shrink-0">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Back home
            </a>
        </div>
    </section>

    <section class="section" aria-label="School calendar">
        <div class="container cal-layout">
            <div x-data="{ view: window.matchMedia('(max-width: 640px)').matches ? 'agenda' : 'month' }">
                <div class="cal-toolbar">
                    <div class="cal-monthnav" aria-label="Change month">
                        <a href="{{ $prevMonth }}" aria-label="Previous month" class="cal-navlink"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                        <h2>{{ $month->translatedFormat('F Y') }}</h2>
                        <a href="{{ $nextMonth }}" aria-label="Next month" class="cal-navlink"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="view-toggle" role="tablist" aria-label="Choose calendar view">
                        <button id="calendar-month-tab" type="button" role="tab" aria-controls="calendar-month-panel"
                            x-ref="monthTab" x-on:click="view = 'month'" x-on:keydown.right.prevent="$refs.agendaTab.focus()"
                            x-on:keydown.left.prevent="$refs.agendaTab.focus()" :aria-selected="view === 'month'"
                            :tabindex="view === 'month' ? 0 : -1">
                            Month view
                        </button>
                        <button id="calendar-agenda-tab" type="button" role="tab" aria-controls="calendar-agenda-panel"
                            x-ref="agendaTab" x-on:click="view = 'agenda'" x-on:keydown.left.prevent="$refs.monthTab.focus()"
                            x-on:keydown.right.prevent="$refs.monthTab.focus()" :aria-selected="view === 'agenda'"
                            :tabindex="view === 'agenda' ? 0 : -1">
                            Agenda view
                        </button>
                    </div>
                </div>

                <div id="calendar-month-panel" role="tabpanel" aria-labelledby="calendar-month-tab" x-show="view === 'month'">
                    @if ($monthEvents->isEmpty())
                        <p class="cal-empty-message">
                            <span class="pending-note"><i class="bi bi-info-circle" aria-hidden="true"></i> No events published for this month.</span>
                        </p>
                    @endif

                    <div class="cal-grid" role="grid" aria-label="Calendar for {{ $month->translatedFormat('F Y') }}">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                            <div class="cal-dow" role="columnheader">{{ $dow }}</div>
                        @endforeach

                        @foreach ($gridDays as $cellDate)
                            @php
                                $isOutside = ! $cellDate->isSameMonth($month);
                                $isToday = $cellDate->isSameDay($today);
                                $isPast = $cellDate->lessThan($today);
                                $dayClass = collect([
                                    'is-outside' => $isOutside,
                                    'is-today' => $isToday,
                                    'is-past' => $isPast && ! $isToday,
                                ])->filter()->keys()->implode(' ');
                                $dayEvents = $byDate->get($cellDate->toDateString(), collect());
                                $dayEventChips = $dayEvents->take(2);
                                $dayEventMore = $dayEvents->count() - $dayEventChips->count();
                            @endphp
                            <div class="cal-day {{ $dayClass }}" role="gridcell"
                                aria-label="{{ $cellDate->format('l, F j, Y') }}{{ $isOutside ? ', adjacent month' : '' }}">
                                <span class="cal-date">{{ $cellDate->day }}</span>
                                @if ($dayEvents->isNotEmpty())
                                    <ul class="cal-events" aria-label="{{ $dayEvents->count() }} published event{{ $dayEvents->count() === 1 ? '' : 's' }} on {{ $cellDate->format('F j, Y') }}">
                                        @foreach ($dayEventChips as $event)
                                            <li class="cal-event ev-{{ $event->type }}" title="{{ $event->title }}">
                                                <span class="sr-only">{{ $typeLabel($event->type) }}: </span>{{ $event->title }}
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($dayEventMore > 0)
                                        <span class="cal-more">+ {{ $dayEventMore }} more</span>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="legend" aria-label="Event types">
                        <span><i class="dot ev-event" aria-hidden="true"></i> School event</span>
                        <span><i class="dot ev-exam" aria-hidden="true"></i> Examination</span>
                        <span><i class="dot ev-holiday" aria-hidden="true"></i> Holiday</span>
                    </div>
                </div>

                <div id="calendar-agenda-panel" role="tabpanel" aria-labelledby="calendar-agenda-tab" x-show="view === 'agenda'">
                    <h2 class="sr-only">Agenda for {{ $month->translatedFormat('F Y') }}</h2>
                    @forelse ($monthEvents as $event)
                        @php
                            $eventDate = \Illuminate\Support\Carbon::parse($event->date, 'Asia/Kathmandu');
                        @endphp
                        @if ($loop->first)
                            <ul class="agenda" aria-label="Published events for {{ $month->translatedFormat('F Y') }}">
                        @endif
                            <li class="ev-{{ $event->type }}">
                                <div class="date-tile" aria-hidden="true">
                                    <strong>{{ $eventDate->format('j') }}</strong>
                                    <span>{{ $eventDate->format('D') }}</span>
                                </div>
                                <div class="agenda-body min-w-0">
                                    <span class="pill pill-blue ev-type-{{ $event->type }}">{{ $typeLabel($event->type) }}</span>
                                    <h3 class="break-words">{{ $event->title }}</h3>
                                    <time datetime="{{ $eventDate->toDateString() }}">{{ $eventDate->format('l, F j, Y') }}</time>
                                    @if ($event->description)
                                        <p class="break-words">{{ $event->description }}</p>
                                    @endif
                                </div>
                            </li>
                        @if ($loop->last)
                            </ul>
                        @endif
                    @empty
                        <p class="pending-note"><i class="bi bi-info-circle" aria-hidden="true"></i> No events published for this month.</p>
                    @endforelse
                </div>
            </div>

            <aside aria-label="Upcoming published events">
                <section class="cal-panel">
                    <h2><i class="bi bi-clock-history" aria-hidden="true"></i> Upcoming events</h2>
                    @if ($upcoming->isEmpty())
                        <p class="panel-note">No upcoming dates have been published.</p>
                    @else
                        <ul class="agenda">
                            @foreach ($upcoming as $event)
                                @php
                                    $eventDate = \Illuminate\Support\Carbon::parse($event->date, 'Asia/Kathmandu');
                                @endphp
                                <li class="ev-{{ $event->type }}">
                                    <div class="date-tile" aria-hidden="true">
                                        <strong>{{ $eventDate->format('j') }}</strong>
                                        <span>{{ $eventDate->format('M') }}</span>
                                    </div>
                                    <div class="agenda-body min-w-0">
                                        <span class="pill pill-blue ev-type-{{ $event->type }}">{{ $typeLabel($event->type) }}</span>
                                        <h3 class="break-words">{{ $event->title }}</h3>
                                        <time datetime="{{ $eventDate->toDateString() }}">{{ $eventDate->format('F j, Y') }}</time>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="cal-panel">
                    <h2><i class="bi bi-tags" aria-hidden="true"></i> Types</h2>
                    <div class="legend legend-col">
                        <span><i class="dot ev-event" aria-hidden="true"></i> School events</span>
                        <span><i class="dot ev-exam" aria-hidden="true"></i> Examinations</span>
                        <span><i class="dot ev-holiday" aria-hidden="true"></i> Holidays</span>
                    </div>
                </section>
            </aside>
        </div>
    </section>
@endsection
