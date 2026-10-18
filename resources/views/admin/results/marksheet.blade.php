<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Marksheet — {{ $exam->name }} — {{ $class->name }}</title>
    @php
        $n = fn($v) => \App\Services\ResultService::n($v);
        $schoolName = config('app.name');
        $schoolAddress = 'School address · Phone · Email'; // change this line
    @endphp
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 0;
            background: #e5e7eb;
        }

        .toolbar {
            padding: 10px 16px;
            background: #0f172a;
            color: #fff;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .toolbar button {
            padding: 6px 14px;
            font-weight: bold;
            cursor: pointer;
            border: 0;
            border-radius: 6px;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto;
            padding: 14mm;
            background: #fff;
            border: 2px solid #111;
            position: relative;
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .head {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .head h1 {
            margin: 0;
            font-size: 22px;
            text-transform: uppercase;
        }

        .head p {
            margin: 2px 0;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            margin: 8px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info {
            width: 100%;
            margin-bottom: 10px;
        }

        .info td {
            padding: 3px 4px;
        }

        table.marks {
            width: 100%;
            border-collapse: collapse;
        }

        table.marks th,
        table.marks td {
            border: 1px solid #111;
            padding: 6px 5px;
            text-align: center;
        }

        table.marks th {
            background: #f1f5f9;
        }

        table.marks td.left {
            text-align: left;
        }

        .fail {
            color: #b91c1c;
            font-weight: bold;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .summary td {
            border: 1px solid #111;
            padding: 6px 8px;
        }

        .summary td.k {
            background: #f1f5f9;
            font-weight: bold;
            width: 16%;
        }

        .scale {
            margin-top: 10px;
            font-size: 10px;
        }

        .scale span {
            display: inline-block;
            border: 1px solid #999;
            padding: 2px 6px;
            margin: 1px;
        }

        .sign {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            text-align: center;
        }

        .sign div {
            width: 28%;
            border-top: 1px solid #111;
            padding-top: 4px;
        }

        .stamp {
            position: absolute;
            right: 14mm;
            top: 150mm;
            font-size: 64px;
            color: rgba(185, 28, 28, .12);
            transform: rotate(-25deg);
            font-weight: bold;
            pointer-events: none;
        }

        @page {
            size: A4;
            margin: 0;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                margin: 0;
                border: 2px solid #111;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨️ Print</button>
        <span>{{ count($rows) }} marksheet(s) · {{ $exam->name }} · {{ $class->name }}</span>
    </div>

    @forelse ($rows as $row)
        <div class="sheet">
            @if ($exam->status !== 'published')
                <div class="stamp">PROVISIONAL</div>
            @endif

            <div class="head">
                <h1>{{ $schoolName }}</h1>
                <p>{{ $schoolAddress }}</p>
            </div>

            <div class="title">Grade Sheet — {{ $exam->name }} ({{ $exam->academic_year }})</div>

            <table class="info">
                <tr>
                    <td><b>Student's Name:</b> {{ $row['name'] }}</td>
                    <td><b>Class:</b> {{ $class->name }}</td>
                </tr>
                <tr>
                    <td><b>Roll No.:</b> {{ $row['roll'] ?: '—' }} &nbsp;&nbsp; <b>Admission No.:</b>
                        {{ $row['admission'] ?: '—' }}</td>
                    <td><b>Date of Issue:</b> {{ now()->format('Y-m-d') }}</td>
                </tr>
            </table>

            <table class="marks">
                <thead>
                    <tr>
                        <th>S.N.</th>
                        <th>Subject</th>
                        <th>Full Marks</th>
                        <th>Pass Marks</th>
                        <th>Theory</th>
                        <th>Practical</th>
                        <th>Total</th>
                        <th>Grade</th>
                        <th>GP</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subjects as $i => $es)
                        @php $r = $row['subjects'][$es->id]; @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="left">{{ $es->subject->name ?? '' }}</td>
                            <td>{{ $n($es->full_marks) }}</td>
                            <td>{{ $n($es->theory_pass + $es->practical_pass) }}</td>
                            <td class="{{ $r['entered'] && !$r['pass'] ? 'fail' : '' }}">
                                {{ $r['absent'] ? 'AB' : ($es->theory_full > 0 ? $n($r['theory']) : '—') }}</td>
                            <td>{{ $r['absent'] ? 'AB' : ($es->practical_full > 0 ? $n($r['practical']) : '—') }}</td>
                            <td><b>{{ $r['absent'] ? 'AB' : ($r['entered'] ? $n($r['total']) : '—') }}</b></td>
                            <td class="{{ $r['entered'] && !$r['pass'] ? 'fail' : '' }}"><b>{{ $r['grade'] }}</b>
                            </td>
                            <td>{{ $r['entered'] ? number_format($r['point'], 1) : '—' }}</td>
                            <td>{{ $r['remark'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="summary">
                <tr>
                    <td class="k">Total Marks</td>
                    <td>{{ $n($row['total']) }} / {{ $n($row['full']) }}</td>
                    <td class="k">Percentage</td>
                    <td>{{ number_format($row['percent'], 2) }}%</td>
                    <td class="k">GPA</td>
                    <td>{{ number_format($row['gpa'], 2) }}</td>
                </tr>
                <tr>
                    <td class="k">Overall Grade</td>
                    <td>{{ $row['overall_grade'] }}</td>
                    <td class="k">Division</td>
                    <td>{{ $row['division'] }}</td>
                    <td class="k">Rank</td>
                    <td>{{ $row['rank'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="k">Result</td>
                    <td colspan="5" class="{{ $row['result'] === 'FAIL' ? 'fail' : '' }}">
                        <b>{{ $row['result'] }}</b>
                        @if ($row['failed'])
                            — not passed in: {{ implode(', ', $row['failed']) }}
                        @endif
                    </td>
                </tr>
            </table>

            <div class="scale">
                <b>Grading scale:</b>
                @foreach (\App\Services\GradeScale::BANDS as [$min, $g, $gp, $remark])
                    <span>{{ $min }}%+ = {{ $g }} ({{ number_format($gp, 1) }})</span>
                @endforeach
                <span>Below 35% / not passed = NG</span> <span>AB = Absent</span>
            </div>

            <div class="sign">
                <div>Class Teacher</div>
                <div>Exam Coordinator</div>
                <div>Principal</div>
            </div>
        </div>
    @empty
        <div class="sheet">
            <p>No students found.</p>
        </div>
    @endforelse
</body>

</html>
