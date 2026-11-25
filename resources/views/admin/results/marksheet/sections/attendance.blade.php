@php
    // Admission-aware per-student attendance coverage.
    //
    // Eligible denominator = the distinct days CLASS attendance was
    // recorded in the exam window, restricted to dates on or after the
    // student's authoritative students.admission_date when that date is
    // valid and available. It counts CLASS-RECORDED attendance dates -
    // NOT every instructional day. Dates before admission are excluded
    // from the denominator AND from the student's numerator, so a
    // legitimately unmarkable pre-admission day can never look like an
    // absence or shrink a fraction.
    //
    // States:
    //   - no class attendance in the window, or no student row on the
    //     eligible dates            => "Not recorded" (never a 0/0 or an
    //     all-absent claim);
    //   - admission valid but NO recorded date falls on/after it
    //                              => "Not recorded" with the reason;
    //   - SOME eligible dates marked => "Partially recorded" + exact
    //     coverage detail - a plain fraction would imply unmarked days
    //     are absences;
    //   - ALL eligible dates marked (any status, absent-only included)
    //                              => genuine present / eligible days.
    // The present/late arithmetic itself is unchanged; the admission
    // filter only restricts WHICH dates are counted.
    $admittedRaw = trim((string) ($student->admission_date ?? ''));
    $admittedOn = null;
    if ($admittedRaw !== '') {
        try {
            $parsed = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $admittedRaw);
            // Round-trip check: 'Y-m-d:H:i' style garbage (e.g. 2083-02-30)
            // shifts when re-formatted and must not be treated as real.
            $admittedOn = ($parsed !== false && $parsed->format('Y-m-d') === $admittedRaw)
                ? $admittedRaw
                : null;
        } catch (\Throwable $e) {
            $admittedOn = null; // missing or invalid => no enrollment date is invented
        }
    }

    $eligibleDates = $admittedOn !== null
        ? $attendanceDates->filter(fn ($d) => $d >= $admittedOn)->values()
        : $attendanceDates;
    $eligibleTotal = $eligibleDates->count();

    $onOrAfter = fn ($dates) => $admittedOn !== null
        ? $dates->filter(fn ($d) => $d >= $admittedOn)->values()
        : $dates->values();
    $studentDates = $onOrAfter($attendanceMarked->get($student->id) ?? collect());
    $studentMarkedDates = $studentDates->count();
    $present = $onOrAfter($attendancePresent->get($student->id) ?? collect())->count();

    $attendanceNote = 'The denominator counts distinct class-recorded attendance dates, not every instructional day. '
        .($attendanceTotal === 0
            ? 'No attendance has been recorded for this class.'
            : ($eligibleTotal === 0
                ? 'No class attendance was recorded on or after the admission on '.$admittedOn.'.'
                : ($studentMarkedDates === 0
                    ? 'Attendance has not been marked for this student on the relevant class-recorded dates.'
                    : '')));

    $attendancePartiallyRecorded = $studentMarkedDates > 0 && $studentMarkedDates < $eligibleTotal;
    if ($attendancePartiallyRecorded) {
        $attendancePartialNote = 'The denominator counts the distinct days class attendance was recorded'
            .($admittedOn !== null
                ? ' on or after the admission on '.$admittedOn
                : '')
            .' (not every instructional day). The student was marked on '
            .$studentMarkedDates.' of '.$eligibleTotal
            .($admittedOn !== null
                ? '; dates before admission could not have attendance rows for this student.'
                : "; the student's admission date is not on record or not usable, so no dates were excluded as pre-admission.");
    } else {
        $attendancePartialNote = '';
    }
@endphp
@if ($layout->showAttendance)
    <table class="meta-row">
        <tr>
            @if ($layout->showHeightWeight)
                <th style="width:30%">Height (cm)</th>
                <td style="width:20%">
                    @if ($heightCm !== null && $heightCm !== false)
                        {{ \App\Services\ResultService::n($heightCm) }}
                    @else
                        <span title="No height measurement recorded for {{ $exam->academic_year }}">Not recorded</span>
                    @endif
                </td>
                <th style="width:30%">Weight (kg)</th>
                <td style="width:20%">
                    @if ($weightKg !== null && $weightKg !== false)
                        {{ \App\Services\ResultService::n($weightKg) }}
                    @else
                        <span title="No weight measurement recorded for {{ $exam->academic_year }}">Not recorded</span>
                    @endif
                </td>
            @else
                <th style="width:40%">Attendance</th>
                <td style="width:60%">
                    @if ($attendancePartiallyRecorded)
                        <span title="{{ $attendancePartialNote }}">Partially recorded</span>
                        <span style="font-size:9px;color:#64748b">
                            (on {{ $studentMarkedDates }} of {{ $eligibleTotal }} class-recorded days)</span>
                    @elseif ($studentMarkedDates > 0)
                        {{ $present }} / {{ $eligibleTotal }} days
                    @else
                        <span title="{{ $attendanceNote }}">Not recorded</span>
                    @endif
                </td>
            @endif
        </tr>
        @if ($layout->showHeightWeight)
            <tr>
                <th>Attendance</th>
                <td colspan="3">
                    @if ($attendancePartiallyRecorded)
                        <span title="{{ $attendancePartialNote }}">Partially recorded</span>
                        <span style="font-size:9px;color:#64748b">
                            (on {{ $studentMarkedDates }} of {{ $eligibleTotal }} class-recorded days)</span>
                    @elseif ($studentMarkedDates > 0)
                        {{ $present }} / {{ $eligibleTotal }} days
                    @else
                        <span title="{{ $attendanceNote }}">Not recorded</span>
                    @endif
                </td>
            </tr>
        @endif
    </table>
@endif