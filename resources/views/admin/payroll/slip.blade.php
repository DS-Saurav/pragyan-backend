<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Salary Slip</title>
    @php
        $m = fn($v) => \App\Support\Money::format($v);
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
            min-height: 148mm;
            margin: 12px auto;
            padding: 12mm 14mm;
            background: #fff;
            border: 1px solid #999;
            position: relative;
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .head {
            text-align: center;
            margin-bottom: 8px;
        }

        .head h1 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
        }

        .head p {
            margin: 2px 0;
        }

        .bar {
            background: #0f172a;
            color: #fff;
            text-align: center;
            font-weight: bold;
            letter-spacing: 1px;
            padding: 6px;
            text-transform: uppercase;
        }

        table.info {
            width: 100%;
            margin: 8px 0;
        }

        table.info td {
            padding: 2px 4px;
        }

        table.pay {
            width: 100%;
            border-collapse: collapse;
        }

        table.pay th {
            background: #0f172a;
            color: #fff;
            padding: 6px 8px;
            text-align: left;
        }

        table.pay td {
            padding: 5px 8px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        table.pay td.r,
        table.pay th.r {
            text-align: right;
        }

        table.pay td.sep {
            border-left: 1px solid #999;
        }

        .net {
            background: #15803d;
            color: #fff;
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            padding: 8px;
            margin-top: 8px;
        }

        .words {
            margin-top: 6px;
            font-style: italic;
        }

        .sign {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            text-align: center;
        }

        .sign div {
            width: 28%;
            border-top: 1px solid #111;
            padding-top: 3px;
        }

        .stamp {
            position: absolute;
            right: 18mm;
            top: 52mm;
            font-size: 46px;
            font-weight: bold;
            transform: rotate(-18deg);
            opacity: .13;
        }

        @page {
            size: A4;
            margin: 8mm;
        }

        @media print {
            body {
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                margin: 0 auto 6mm;
                border: 1px solid #999;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <button onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <span>{{ $slips->count() }} salary slip(s)</span>
    </div>

    @foreach ($slips as $s)
        @php
            $earn = $s->lines->where('type', 'earning')->values();
            $ded = $s->lines->where('type', 'deduction')->values();
            $rows = max($earn->count() + 1, $ded->count());
        @endphp
        <div class="sheet">
            @if ($s->status === 'paid')
                <div class="stamp" style="color:#15803d">PAID</div>
            @endif

            <div class="head">
                <h1>{{ config('app.name') }}</h1>
                <p>{{ $schoolAddress }}</p>
            </div>

            <div class="bar">Salary slip — {{ \App\Models\SalarySlip::monthLabel($s->month) }}</div>

            <table class="info">
                <tr>
                    <td><b>Employee:</b> {{ $s->staff_name }}</td>
                    <td><b>Designation:</b> {{ $s->designation ?: '—' }}</td>
                </tr>
                <tr>
                    <td><b>Slip No:</b> {{ $s->slip_no }}</td>
                    <td><b>Attendance:</b>
                        {{ rtrim(rtrim(number_format($s->working_days - $s->unpaid_days, 1, '.', ''), '0'), '.') }} /
                        {{ $s->working_days }} days</td>
                </tr>
                <tr>
                    <td><b>PAN:</b> {{ $s->staff->pan_no ?? '—' }}</td>
                    <td>
                        <b>Status:</b>
                        @if ($s->status === 'paid')
                            paid on {{ $s->paid_on?->format('Y-m-d') }} ·
                            {{ \App\Support\Money::MODES[$s->payment_mode] ?? '' }}{{ $s->reference_no ? ' · Ref ' . $s->reference_no : '' }}
                        @else
                            not paid yet
                        @endif
                    </td>
                </tr>
            </table>

            <table class="pay">
                <thead>
                    <tr>
                        <th>Earnings</th>
                        <th class="r">Amount</th>
                        <th>Deductions</th>
                        <th class="r">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        @php
                            $e = $i === 0 ? ['name' => 'Basic Salary', 'amount' => $s->basic] : $earn[$i - 1] ?? null;
                            $d = $ded[$i] ?? null;
                        @endphp
                        <tr>
                            <td>{{ is_array($e) ? $e['name'] : $e->name ?? '' }}</td>
                            <td class="r">{{ $e ? $m(is_array($e) ? $e['amount'] : $e->amount) : '' }}</td>
                            <td class="sep">{{ $d->name ?? '' }}</td>
                            <td class="r">{{ $d ? $m($d->amount) : '' }}</td>
                        </tr>
                    @endfor
                    <tr>
                        <td><b>Gross Earnings</b></td>
                        <td class="r"><b>{{ $m($s->gross) }}</b></td>
                        <td class="sep"><b>Total Deductions</b></td>
                        <td class="r"><b>{{ $m($s->total_deductions) }}</b></td>
                    </tr>
                </tbody>
            </table>

            <div class="net">NET PAY: {{ $m($s->net_pay) }}</div>
            <p class="words"><b>In words:</b> {{ \App\Support\Money::words($s->net_pay) }}</p>
            @if ($s->remarks)
                <p><b>Remarks:</b> {{ $s->remarks }}</p>
            @endif

            <div class="sign">
                <div>Employee Signature</div>
                <div>Accounts Officer</div>
                <div>Principal</div>
            </div>
        </div>
    @endforeach
</body>

</html>
