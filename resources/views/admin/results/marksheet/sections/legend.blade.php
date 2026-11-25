@if ($layout->showLegend)
    <table class="scale-table">
        <thead>
            <tr>
                <th>Intervals</th>
                <th>Grade</th>
                <th>Grade Point</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Services\GradeScale::BANDS as [$min, $g, $gp, $remark])
                <tr>
                    <td>{{ $min }}% and above</td>
                    <td>{{ $g }}</td>
                    <td>{{ number_format($gp, 1) }}</td>
                </tr>
            @endforeach
            <tr>
                <td>Below {{ \App\Services\GradeScale::BANDS[count(\App\Services\GradeScale::BANDS) - 1][0] ?? 35 }}%</td>
                <td>NG</td>
                {{-- NG has no grade point per Annexure-1 of the CDC directive --}}
                <td>-</td>
            </tr>
        </tbody>
    </table>
@endif
