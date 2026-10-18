<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Result Ledger — {{ $exam->name }} — {{ $class->name }}</title>
    @php $n = fn ($v) => \App\Services\ResultService::n($v); @endphp
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
            padding: 14px;
        }

        .toolbar {
            margin-bottom: 10px;
        }

        .toolbar button {
            padding: 6px 14px;
            font-weight: bold;
            cursor: pointer;
        }

        h1 {
            text-align: center;
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
        }

        .sub {
            text-align: center;
            margin: 4px 0 10px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #111;
            padding: 5px 4px;
            text-align: center;
        }

        th {
            background: #f1f5f9;
        }

        td.left {
            text-align: left;
        }

        .fail {
            color: #b91c1c;
            font-weight: bold;
        }

        .sign {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            text-align: center;
        }

        .sign div {
            width: 25%;
            border-top: 1px solid #111;
            padding-top: 4px;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        @media print {
            .toolbar {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar"><button onclick="window.print()">🖨️ Print</button></div>

    <h1>{{ config('app.name') }}</h1>
    <div class="sub">
        Result Ledger — {{ $exam->name }} ({{ $exam->academic_year }}) — {{ $class->name }}
        @if ($exam->status !== 'published')
            <span class="fail">[PROVISIONAL]</span>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Roll</th>
                <th>Student</th>
                @foreach ($subjects as $es)
                    <th>{{ $es->subject->name ?? '' }}<br><small>/{{ $n($es->full_marks) }}</small></th>
                @endforeach
                <th>Total</th>
                <th>%</th>
                <th>GPA</th>
                <th>Division</th>
                <th>Rank</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['roll'] }}</td>
                    <td class="left">{{ $row['name'] }}</td>
                    @foreach ($subjects as $es)
                        @php $r = $row['subjects'][$es->id]; @endphp
                        <td class="{{ $r['entered'] && !$r['pass'] ? 'fail' : '' }}">
                            {{ $r['absent'] ? 'AB' : ($r['entered'] ? $n($r['total']) . ' (' . $r['grade'] . ')' : '—') }}
                        </td>
                    @endforeach
                    <td><b>{{ $n($row['total']) }}</b></td>
                    <td>{{ number_format($row['percent'], 1) }}</td>
                    <td>{{ number_format($row['gpa'], 2) }}</td>
                    <td>{{ $row['division'] }}</td>
                    <td>{{ $row['rank'] ?? '—' }}</td>
                    <td class="{{ $row['result'] === 'FAIL' ? 'fail' : '' }}"><b>{{ $row['result'] }}</b></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top:10px">
        <b>Students:</b> {{ $summary['students'] }} &nbsp;|&nbsp;
        <b>Passed:</b> {{ $summary['passed'] }} &nbsp;|&nbsp;
        <b>Failed:</b> {{ $summary['failed'] }} &nbsp;|&nbsp;
        <b>Pass %:</b> {{ $summary['pass_percent'] }}% &nbsp;|&nbsp;
        <b>Highest total:</b> {{ $n($summary['highest']) }}
    </p>

    <div class="sign">
        <div>Prepared by</div>
        <div>Exam Coordinator</div>
        <div>Principal</div>
    </div>
</body>

</html>
