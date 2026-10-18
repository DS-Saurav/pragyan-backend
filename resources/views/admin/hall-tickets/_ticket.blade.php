{{-- resources/views/admin/hall-tickets/_ticket.blade.php --}}
<div class="ticket" data-id="{{ $t['id'] }}">

    {{-- School header --}}
    <div class="t-head">
        <div class="logo">
            @if ($school['logo'])
                <img src="{{ $school['logo'] }}" alt="">
            @else
                🏫
            @endif
        </div>
        <div class="school">
            <h2>{{ $school['name'] }}</h2>
            @if ($school['address'])
                <p>{{ $school['address'] }}</p>
            @endif
            @if ($school['phone'] || $school['email'])
                <p>{{ collect([$school['phone'], $school['email']])->filter()->implode('  |  ') }}</p>
            @endif
        </div>
    </div>

    <div class="t-title">Examination Hall Ticket</div>

    <div class="exam-line">
        {{ $examMeta['name'] }}
        @if ($examMeta['year'])
            <span>({{ $examMeta['year'] }})</span>
        @endif
        @if ($examMeta['start'] || $examMeta['end'])
            <span>&nbsp;|&nbsp; {{ $examMeta['start'] ?? '—' }} to {{ $examMeta['end'] ?? '—' }}</span>
        @endif
    </div>

    {{-- Student details --}}
    <div class="t-body">
        <div class="photo">
            @if ($t['photo'])
                <img src="{{ $t['photo'] }}" alt="">
            @else
                Paste<br>passport<br>photo
            @endif
        </div>

        <div class="info">
            <div class="full"><b>Student:</b> {{ $t['name'] }}</div>
            <div><b>Class:</b> {{ $t['class'] }}</div>
            <div><b>Roll No:</b> {{ $t['roll'] }}</div>
            <div><b>Admission #:</b> {{ $t['adm'] }}</div>
            <div><b>Gender:</b> {{ $t['gender'] ?? '—' }}</div>
            <div class="full"><b>Date of Birth:</b> {{ $t['dob'] ?? '—' }}</div>
        </div>

        <div class="qr" data-qr="HT|{{ $examMeta['id'] }}|{{ $t['adm'] }}"></div>
    </div>

    {{-- Exam schedule --}}
    @if (!empty($t['subjects']))
        <table>
            <thead>
                <tr>
                    <th style="width:5%">#</th>
                    <th>Subject</th>
                    <th style="width:17%">Date</th>
                    <th style="width:14%">Day</th>
                    <th style="width:20%">Time</th>
                    <th style="width:16%">Invigilator</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($t['subjects'] as $i => $sub)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><b>{{ $sub['name'] }}</b></td>
                        <td>{{ $sub['date'] ?? '' }}</td>
                        <td>{{ $sub['day'] ?? '' }}</td>
                        <td>{{ $sub['time'] ?? '' }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-sched">Exam schedule is not set up for this class.</div>
    @endif

    {{-- Instructions --}}
    <div class="instructions">
        <b>Instructions to candidates:</b>
        <ol>
            @foreach ($instructions as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ol>
    </div>

    {{-- Signatures --}}
    <div class="sign">
        <div>Student's Signature</div>
        <div>Class Teacher</div>
        <div>Exam Coordinator</div>
        <div>Principal</div>
    </div>

    <div class="foot">Issued on {{ now()->format('d M Y') }}</div>
</div>
