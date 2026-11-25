@php
    $failedCount = count($row['failed']);
    // Overall conditions come straight from ResultService: PASS/FAIL/INCOMPLETE.
    // Display rules are per-condition and cannot affect subject grades, the
    // legend, or the computed result itself (only whether cells are shown).
    $show = fn (string $key) => $layout->gpaFlagFor(strtolower($row['result'] ?? ''), $key);
    $gpaBasis = ($row['gpa_basis'] ?? 'unweighted') === 'credit_hour' ? '' : ' (unweighted)';
    $failText = $row['result'] === 'FAIL' && $failedCount ? ' (Not passed in: '.implode(', ', $row['failed']).')' : '';
    // No cells to show: skip the block entirely - no empty bordered table.
    $hasCells = $show('gpa') || $show('grade') || $show('division') || $show('total_marks')
        || $show('percentage') || $show('rank') || $show('result_label');
@endphp
@if ($hasCells)
    <table class="gpa-summary">
        <tr>
            @if ($show('gpa'))<td>GPA{{ $gpaBasis }}: {{ number_format($row['gpa'], 2) }}</td>@endif
            @if ($show('grade'))<td>Grade: {{ $row['overall_grade'] }}</td>@endif
            @if ($show('division'))<td>Division: {{ $row['division'] }}</td>@endif
            @if ($show('total_marks'))<td>Total Marks: {{ $n($row['total']) }} / {{ $n($row['full']) }}</td>@endif
            @if ($show('percentage'))<td>Percentage: {{ number_format($row['percent'], 2) }}%</td>@endif
            @if ($show('rank'))<td>Rank: {{ $row['rank'] ?? '-' }}</td>@endif
            @if ($show('result_label'))
                <td>Result: <span class="{{ $row['result'] === 'FAIL' ? 'fail' : '' }}">{{ $row['result'] }}</span>{{ $failText }}</td>
            @endif
        </tr>
    </table>
@endif
