<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receipt->receipt_no }}</title>
    @php
        $m = fn($v) => 'Rs. ' . number_format((float) $v, fmod((float) $v, 1) ? 2 : 0);
        $schoolAddress = 'School address · Phone · Email'; // change this line
        $studentName = \App\Services\ResultService::studentName($receipt->student);
        $copies = request('copies') == 1 ? ['Student Copy'] : ['Student Copy', 'Office Copy'];
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

        .toolbar button,
        .toolbar a {
            padding: 6px 14px;
            font-weight: bold;
            cursor: pointer;
            border: 0;
            border-radius: 6px;
            background: #fff;
            color: #111;
            text-decoration: none;
            font-size: 12px;
        }

        .page {
            width: 210mm;
            margin: 12px auto;
            background: #fff;
        }

        .copy {
            padding: 10mm 12mm;
            border-bottom: 1px dashed #666;
            position: relative;
            page-break-inside: avoid;
        }

        .copy:last-child {
            border-bottom: 0;
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 6px;
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

        .tag {
            border: 1px solid #111;
            padding: 2px 8px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            letter-spacing: 1px;
            margin: 6px 0;
            text-transform: uppercase;
        }

        table.info {
            width: 100%;
            margin-bottom: 8px;
        }

        table.info td {
            padding: 2px 4px;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
        }

        table.lines th,
        table.lines td {
            border: 1px solid #111;
            padding: 4px 6px;
        }

        table.lines th {
            background: #f1f5f9;
            text-align: left;
        }

        .r {
            text-align: right;
        }

        .totals {
            width: 55%;
            margin-left: auto;
            margin-top: 6px;
            border-collapse: collapse;
        }

        .totals td {
            padding: 2px 6px;
        }

        .totals tr.big td {
            border-top: 1px solid #111;
            font-weight: bold;
        }

        .words {
            margin-top: 6px;
            font-style: italic;
        }

        .sign {
            display: flex;
            justify-content: space-between;
            margin-top: 26px;
            text-align: center;
        }

        .sign div {
            width: 30%;
            border-top: 1px solid #111;
            padding-top: 3px;
            font-size: 11px;
        }

        .stamp {
            position: absolute;
            right: 18mm;
            top: 38mm;
            font-size: 44px;
            font-weight: bold;
            transform: rotate(-18deg);
            opacity: .15;
        }

        .foot {
            margin-top: 6px;
            font-size: 10px;
            color: #555;
            text-align: center;
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
    <div class="toolbar">
        <button onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        @if (request('copies') == 1)
            <a href="{{ route('admin.fees.print', $receipt) }}">Show both copies</a>
        @else
            <a href="{{ route('admin.fees.print', [$receipt, 'copies' => 1]) }}">Single copy</a>
        @endif
    </div>

    <div class="page">
        @foreach ($copies as $copy)
            <div class="copy">
                @if ($receipt->status === 'refunded')
                    <div class="stamp" style="color:#b91c1c">REFUNDED</div>
                @elseif ($receipt->status === 'paid')
                    <div class="stamp" style="color:#15803d">PAID</div>
                @elseif ($receipt->status === 'partial')
                    <div class="stamp" style="color:#b45309">PARTIAL</div>
                @endif

                <div class="head">
                    <div>
                        <h1>{{ config('app.name') }}</h1>
                        <p>{{ $schoolAddress }}</p>
                    </div>
                    <div class="tag">{{ $copy }}</div>
                </div>

                <div class="title">Fee Receipt</div>

                <table class="info">
                    <tr>
                        <td><b>Receipt No:</b> {{ $receipt->receipt_no }}</td>
                        <td class="r"><b>Date:</b> {{ $receipt->receipt_date->format('Y-m-d') }}</td>
                    </tr>
                    <tr>
                        <td><b>Student:</b> {{ $studentName }}</td>
                        <td class="r"><b>Class:</b> {{ $class->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td><b>Admission No:</b> {{ \App\Services\ResultService::admission($receipt->student) ?: '—' }}
                        </td>
                        <td class="r"><b>Academic year:</b> {{ $receipt->academic_year }}</td>
                    </tr>
                </table>

                <table class="lines">
                    <thead>
                        <tr>
                            <th style="width:8%">S.N.</th>
                            <th>Particulars</th>
                            <th style="width:20%">Period</th>
                            <th class="r" style="width:20%">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receipt->items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->period ?: '—' }}</td>
                                <td class="r">{{ $m($item->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table class="totals">
                    <tr>
                        <td>Fee total</td>
                        <td class="r">{{ $m($receipt->subtotal) }}</td>
                    </tr>
                    @if ($receipt->discount > 0)
                        <tr>
                            <td>Discount</td>
                            <td class="r">− {{ $m($receipt->discount) }}</td>
                        </tr>
                    @endif
                    @if ($receipt->fine > 0)
                        <tr>
                            <td>Late fine</td>
                            <td class="r">+ {{ $m($receipt->fine) }}</td>
                        </tr>
                    @endif
                    <tr class="big">
                        <td>Total due</td>
                        <td class="r">{{ $m($receipt->total_due) }}</td>
                    </tr>
                    <tr>
                        <td>Amount paid</td>
                        <td class="r">{{ $m($receipt->paid_amount) }}</td>
                    </tr>
                    <tr class="big">
                        <td>Balance</td>
                        <td class="r">{{ $m($receipt->balance) }}</td>
                    </tr>
                </table>

                <p class="words"><b>In words (paid):</b>
                    {{ \App\Models\FeeReceipt::amountInWords($receipt->paid_amount) }}</p>

                @if ($receipt->payments->isNotEmpty())
                    <table class="lines" style="margin-top:6px;">
                        <thead>
                            <tr>
                                <th>Payment date</th>
                                <th>Mode</th>
                                <th>Reference</th>
                                <th class="r">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($receipt->payments->sortBy('id') as $p)
                                <tr>
                                    <td>{{ $p->paid_on->format('Y-m-d') }}</td>
                                    <td>{{ \App\Models\FeeReceipt::MODES[$p->mode] ?? $p->mode }}</td>
                                    <td>{{ $p->reference_no ?: '—' }}</td>
                                    <td class="r">{{ $m($p->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if ($receipt->remarks)
                    <p><b>Remarks:</b> {{ $receipt->remarks }}</p>
                @endif
                @if ($receipt->status === 'refunded')
                    <p><b>Refunded:</b> {{ $receipt->refund_reason }}</p>
                @endif

                <div class="sign">
                    <div>Received by<br>{{ $receipt->collector->name ?? '' }}</div>
                    <div>Parent / Guardian</div>
                    <div>Accountant</div>
                </div>

                <div class="foot">Computer generated receipt. Fees once paid are not refundable unless approved by the
                    principal.</div>
            </div>
        @endforeach
    </div>
</body>

</html>
