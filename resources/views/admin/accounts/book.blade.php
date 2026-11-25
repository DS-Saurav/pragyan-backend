<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Day Book {{ $from }} to {{ $to }}</title>
    @php
        $n = fn($v) => number_format((float) $v, 2);
    @endphp
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
            padding: 4px 5px;
        }

        th {
            background: #f1f5f9;
            text-align: left;
        }

        td.r,
        th.r {
            text-align: right;
        }

        tr.total td {
            font-weight: bold;
            background: #f8fafc;
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
    <div class="toolbar"><button onclick="window.print()"><i class="bi bi-printer"></i> Print</button></div>

    <h1>{{ config('app.name') }}</h1>
    <div class="sub">Day Book / Cash Book — {{ $from }} to {{ $to }}</div>

    <table>
        <thead>
            <tr>
                <th style="width:9%">Date</th>
                <th>Particulars</th>
                <th style="width:13%">Category</th>
                <th style="width:9%">Mode</th>
                <th class="r" style="width:12%">Receipts (Rs.)</th>
                <th class="r" style="width:12%">Payments (Rs.)</th>
                <th class="r" style="width:12%">Balance (Rs.)</th>
            </tr>
        </thead>
        <tbody>
            <tr class="total">
                <td colspan="6">Opening balance (brought forward)</td>
                <td class="r">{{ $n($opening) }}</td>
            </tr>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['date']->format('Y-m-d') }}</td>
                    <td>{{ $r['description'] }}{{ $r['ref'] ? ' (Ref ' . $r['ref'] . ')' : '' }}</td>
                    <td>{{ $r['category'] }}</td>
                    <td>{{ \App\Support\Money::MODES[$r['mode']] ?? $r['mode'] }}</td>
                    <td class="r">{{ $r['type'] === 'income' ? $n($r['amount']) : '' }}</td>
                    <td class="r">{{ $r['type'] === 'expense' ? $n($r['amount']) : '' }}</td>
                    <td class="r">{{ $n($r['balance']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="4">Total for the period</td>
                <td class="r">{{ $n($receipts) }}</td>
                <td class="r">{{ $n($payments) }}</td>
                <td></td>
            </tr>
            <tr class="total">
                <td colspan="6">Closing balance</td>
                <td class="r">{{ $n($balance) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="sign">
        <div>Prepared by</div>
        <div>Accountant</div>
        <div>Principal</div>
    </div>
</body>

</html>
