<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Discipline Notice — {{ \App\Services\ResultService::studentName($incident->student) }}</title>
    @php
        $studentName = \App\Services\ResultService::studentName($incident->student);
        $schoolAddress = 'School address · Phone · Email'; // change this line
    @endphp
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            color: #111;
            margin: 0;
            background: #e5e7eb;
        }

        .toolbar {
            padding: 10px 16px;
            background: #0f172a;
            color: #fff;
        }

        .toolbar button {
            padding: 6px 14px;
            font-weight: bold;
            cursor: pointer;
            border: 0;
            border-radius: 6px;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto;
            padding: 18mm;
            background: #fff;
        }

        .head {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 8px;
            margin-bottom: 18px;
        }

        .head h1 {
            margin: 0;
            font-size: 22px;
            text-transform: uppercase;
        }

        .head p {
            margin: 2px 0;
        }

        h2 {
            text-align: center;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 14px 0;
        }

        table.info {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
        }

        table.info td {
            border: 1px solid #111;
            padding: 7px 9px;
            vertical-align: top;
        }

        table.info td.k {
            background: #f1f5f9;
            font-weight: bold;
            width: 28%;
        }

        p {
            line-height: 1.6;
        }

        .sign {
            display: flex;
            justify-content: space-between;
            margin-top: 70px;
            text-align: center;
        }

        .sign div {
            width: 30%;
            border-top: 1px solid #111;
            padding-top: 4px;
        }

        .ack {
            margin-top: 40px;
            border-top: 1px dashed #666;
            padding-top: 12px;
            font-size: 12px;
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

            .page {
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar"><button onclick="window.print()">🖨️ Print notice</button></div>

    <div class="page">
        <div class="head">
            <h1>{{ config('app.name') }}</h1>
            <p>{{ $schoolAddress }}</p>
        </div>

        <p>Date: {{ now()->format('Y-m-d') }}</p>
        <p>To,<br><b>The Parent / Guardian of {{ $studentName }}</b><br>Class: {{ $class->name ?? '—' }}</p>

        <h2>Notice: Student Conduct</h2>

        <p>Dear Parent / Guardian,</p>
        <p>We are writing to inform you about an incident involving your child at school. We value your support in
            helping every student learn in a safe and respectful environment.</p>

        <table class="info">
            <tr>
                <td class="k">Date of incident</td>
                <td>{{ $incident->incident_date->format('Y-m-d') }}</td>
            </tr>
            <tr>
                <td class="k">Incident</td>
                <td>{{ $incident->title }}</td>
            </tr>
            <tr>
                <td class="k">Category</td>
                <td>{{ \App\Models\DisciplineIncident::CATEGORIES[$incident->category] ?? $incident->category }}</td>
            </tr>
            @if ($incident->description)
                <tr>
                    <td class="k">Details</td>
                    <td>{!! nl2br(e($incident->description)) !!}</td>
                </tr>
            @endif
            <tr>
                <td class="k">Action taken by school</td>
                <td>
                    {{ \App\Models\DisciplineIncident::ACTIONS[$incident->action_type] ?? 'Under review' }}
                    @if ($incident->action_taken)
                        — {{ $incident->action_taken }}
                    @endif
                </td>
            </tr>
            @if ($incident->follow_up_date)
                <tr>
                    <td class="k">Follow-up</td>
                    <td>Please meet the class teacher on or before {{ $incident->follow_up_date->format('Y-m-d') }}.
                    </td>
                </tr>
            @endif
        </table>

        <p>We kindly request you to talk with your child about this matter and to contact the school if you would like
            to discuss it further. Thank you for your cooperation.</p>

        <div class="sign">
            <div>Class Teacher</div>
            <div>Discipline Coordinator</div>
            <div>Principal</div>
        </div>

        <div class="ack">
            <b>Parent / Guardian acknowledgement</b><br><br>
            I have read this notice.<br><br>
            Name: ____________________________ &nbsp;&nbsp; Signature: ____________________ &nbsp;&nbsp; Date:
            ____________
        </div>
    </div>
</body>

</html>
