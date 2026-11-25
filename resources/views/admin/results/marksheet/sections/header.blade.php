@php
    $showLogo = $layout->flag('header', 'show_logo');
@endphp
<div>
    <div class="head">
        @if ($showLogo)
            <div class="crest">
                @if (! empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="School logo">
                @else
                    <span class="head-note" style="text-align:center">LOGO</span>
                @endif
            </div>
        @endif
        <div style="text-align:center">
            <h1 class="brand" style="margin:0">{{ $schoolName }}</h1>
            @if ($schoolAddress)<p>{{ $schoolAddress }}</p>@endif
            <p>
                @if ($schoolPhone)Phone: {{ $schoolPhone }}@endif
                @if ($schoolPhone && $schoolEmail) &nbsp;·&nbsp; @endif
                @if ($schoolEmail){{ $schoolEmail }}@endif
            </p>
        </div>
        @if ($layout->flag('header', 'show_photo'))
            <div class="photo">
                @php $photoUrl = \App\Support\Photo::url($student->photo ?? null); @endphp
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Student photo">
                @else
                    <span class="head-note" style="text-align:center">No<br>Photo</span>
                @endif
            </div>
        @endif
    </div>

    <div class="exam-title">{{ $exam->name }}</div>
    <div class="grade-sheet-title">{{ $layout->label('exam_title', 'GRADE SHEET') }}</div>
</div>
