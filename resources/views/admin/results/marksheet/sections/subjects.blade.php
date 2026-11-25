@php
    $cols = fn (string $key) => $layout->flag('columns', $key);
    // Authoritative CH for a row (exam_subjects.credit_hours), with the
    // marksheet-layout map as a display-only fallback - it never feeds
    // the GPA calculation.
    $ch = function ($es, $code) use ($layout) {
        $chData = (is_numeric($es->credit_hours ?? null) && (float) $es->credit_hours > 0)
            ? (float) $es->credit_hours
            : null;

        return $chData !== null
            ? \App\Services\ResultService::n($chData)
            : $layout->creditHour($code);
    };
@endphp
@if ($layout->showComponentMarks)
    {{-- Stage P3J: reference-style separate component rows. Each subject
         prints one row per offered component (TH / IN) plus merged
         subject-level cells via rowspan, so the aggregate Grade, credit
         hours and remark stay aligned - never duplicated per row. The
         aggregate values come from the SAME subjectResult() data as the
         single-row mode; component grade/point are display-only extras. --}}
    @php
        // No closure indirection: $row['subjects'] is keyed by exam subject id.
    @endphp
    <table class="marks">
        <thead>
            <tr>
                @if ($cols('sn'))<th>S.N.</th>@endif
                @if ($cols('subject_code'))<th>Subject Code</th>@endif
                <th>Subjects</th>
                @if ($cols('credit_hour'))<th>Credit Hour (CH)</th>@endif
                <th style="width:30px">TH/IN</th>
                <th>Full Marks</th>
                <th>Pass Marks</th>
                <th>Marks Obtained</th>
                @if ($cols('grade_point'))<th>Grade Point (GP)</th>@endif
                @if ($cols('grade'))<th>Grade</th>@endif
                @if ($cols('final_grade'))<th>Final Grade</th>@endif
                @if ($cols('remarks'))<th>Remarks</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($subjects as $i => $es)
                @php
                    $r = $row['subjects'][$es->id];
                    $code = $es->subject->code ?? null;
                    $chText = $ch($es, $code);
                    $comps = $r['components'];
                    $rs = max(1, count($comps));
                    // Merged subject-level cells span every component row;
                    // with a single component no rowspan attribute is needed.
                    $attrRowspan = $rs > 1 ? 'rowspan="'.$rs.'"' : '';
                    // Aggregate cells (identical to the single-row mode).
                    $finalGrade = $r['entered'] ? $r['grade'] : '-';
                    $lastCellClass = $r['entered'] && !$r['pass'] ? 'fail' : '';
                @endphp
                @foreach ($comps as $ci => $c)
                    <tr>
                        @if ($cols('sn') && $ci === 0)<td {!! $attrRowspan !!}>{{ $i + 1 }}</td>@endif
                        @if ($cols('subject_code') && $ci === 0)<td {!! $attrRowspan !!}>{{ $code ?? '-' }}</td>@endif
                        @if ($ci === 0)
                            <td class="left" {!! $attrRowspan !!}>{{ $es->subject->name ?? '' }}</td>
                        @endif
                        @if ($cols('credit_hour') && $ci === 0)<td {!! $attrRowspan !!}>{{ $chText ?? '-' }}</td>@endif
                        <td>{{ $c['label'] }}</td>
                        <td>{{ \App\Services\ResultService::n($c['full']) }}</td>
                        <td>{{ \App\Services\ResultService::n($c['pass']) }}</td>
                        <td>{{ \App\Services\ResultService::n($c['obtained']) }}</td>
                        @if ($cols('grade_point'))<td>{{ $c['point'] !== null ? number_format($c['point'], 1) : '-' }}</td>@endif
                        @if ($cols('grade'))<td><b>{{ $c['grade'] }}</b></td>@endif
                        @if ($cols('final_grade') && $ci === 0)<td class="merged-cell" {!! $attrRowspan !!}>{{ $finalGrade }}</td>@endif
                        @if ($cols('remarks') && $ci === 0)<td {!! $attrRowspan !!} class="{{ $lastCellClass }}">{{ $r['remark'] ?? '-' }}</td>@endif
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
@else
    {{-- Single-row mode (default, legacy structure unchanged). --}}
    <table class="marks">
        <thead>
            <tr>
                @if ($cols('sn'))<th>S.N.</th>@endif
                @if ($cols('subject_code'))<th>Subject Code</th>@endif
                <th>Subjects</th>
                @if ($cols('credit_hour'))<th>Credit Hour (CH)</th>@endif
                @if ($cols('grade_point'))<th>Grade Point (GP)</th>@endif
                @if ($cols('grade'))<th>Grade</th>@endif
                @if ($cols('final_grade'))<th>Final Grade</th>@endif
                @if ($cols('remarks'))<th>Remarks</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($subjects as $i => $es)
                @php
                    $r = $row['subjects'][$es->id];
                    $code = $es->subject->code ?? null;
                    $chText = $ch($es, $code);
                    // Final grade merged per subject: this system combines theory
                    // and internal components into one graded row, so the merged
                    // final grade is the subject's own grade - no value invented.
                @endphp
                <tr>
                    @if ($cols('sn'))<td>{{ $i + 1 }}</td>@endif
                    @if ($cols('subject_code'))<td>{{ $code ?? '-' }}</td>@endif
                    <td class="left">
                        {{ $es->subject->name ?? '' }}
                    </td>
                    @if ($cols('credit_hour'))<td>{{ $chText ?? '-' }}</td>@endif
                    @if ($cols('grade_point'))<td>{{ $r['point'] !== null ? number_format($r['point'], 1) : '-' }}</td>@endif
                    <td><b>{{ $r['entered'] ? $r['grade'] : '-' }}</b></td>
                    @if ($cols('final_grade'))
                        <td class="merged-cell">{{ $r['entered'] ? $r['grade'] : '-' }}</td>
                    @endif
                    @if ($cols('remarks'))
                        <td class="{{ $r['entered'] ? ($r['pass'] ? '' : 'fail') : '' }}">{{ $r['remark'] ?? '-' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
