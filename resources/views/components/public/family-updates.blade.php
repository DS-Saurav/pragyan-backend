@php
    $admissionLabel = $cycle ? (\App\Models\AdmissionCycle::PUBLIC_STATUSES[$cycle->public_status] ?? 'Contact us') : 'Contact the school';
    $calendarUrl = $nextEvent
        ? route('public.calendar', ['month' => \Illuminate\Support\Carbon::parse($nextEvent->date)->format('Y-m')])
        : route('public.calendar');
    $noticeUrl = $latestNotice?->slug
        ? route('public.notices.show', ['slug' => $latestNotice->slug])
        : route('public.notices');
@endphp

<section class="family-briefing" aria-labelledby="familyUpdatesTitle">
    <div class="container">
        <div class="family-briefing__head">
            <span class="eyebrow">For families</span>
            <h2 id="familyUpdatesTitle">What to know now</h2>
        </div>
        <div class="family-briefing__layout">
            <a href="{{ route('public.admissions') }}#current-status" class="family-briefing__lead">
                <span class="family-briefing__lead-icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
                <span class="family-briefing__lead-copy">
                    <span class="family-briefing__label">Admissions status</span>
                    <strong>{{ $cycle?->label ?? 'Admissions information' }}</strong>
                    <span>{{ $cycle ? $admissionLabel : 'Contact the school for current admissions information.' }}</span>
                </span>
                <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>

            <div class="family-briefing__rows">
                <a href="{{ $calendarUrl }}" class="family-briefing__row">
                    <span class="family-briefing__row-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                    <span>
                        <span class="family-briefing__label">Next important date</span>
                        @if ($nextEvent)
                            <strong>{{ $nextEvent->title }}</strong>
                            <span><time datetime="{{ $nextEvent->date }}">{{ \Illuminate\Support\Carbon::parse($nextEvent->date)->format('j M Y') }}</time></span>
                        @else
                            <strong>No upcoming date published</strong>
                            <span>Check the school calendar for future updates.</span>
                        @endif
                    </span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>

                <a href="{{ $noticeUrl }}" class="family-briefing__row">
                    <span class="family-briefing__row-icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
                    <span>
                        <span class="family-briefing__label">Latest notice</span>
                        @if ($latestNotice)
                            <strong>{{ \Illuminate\Support\Str::limit($latestNotice->title, 86) }}</strong>
                            <span><time datetime="{{ $latestNotice->date }}">{{ \Illuminate\Support\Carbon::parse($latestNotice->date)->format('j M Y') }}</time></span>
                        @else
                            <strong>No current notice published</strong>
                            <span>Visit the notice board when the school shares an update.</span>
                        @endif
                    </span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    </div>
</section>
