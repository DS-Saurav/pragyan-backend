@if ($layout->showAssessment && count($layout->assessmentRows))
    <table class="assessment">
        <thead>
            <tr>
                <th colspan="2">Assessment Indicators</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            {{-- Ratings saved per exam and student, keyed by the layout's
                 STABLE indicator key (Stage P3I); labels come from the
                 configured scale. Unset = "Not recorded" - never a
                 fabricated or auto-assigned rating. --}}
            @foreach ($layout->assessmentRows as $indicator)
                @php
                    $assessmentScale = config('school.assessments.scale', []);
                    $assessmentRating = $studentAssessments[$indicator['key']] ?? null;
                    $assessmentLabel = $assessmentRating !== null
                        ? ($assessmentScale[$assessmentRating] ?? $assessmentRating)
                        : null;
                @endphp
                <tr>
                    <th colspan="2">{{ $indicator['label'] }}</th>
                    <td class="grade" @if ($assessmentLabel === null) title="No assessment recorded for this indicator on this examination." @endif>
                        {{ $assessmentLabel ?? 'Not recorded' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
