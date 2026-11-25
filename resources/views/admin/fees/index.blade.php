@extends('layouts.admin')

@section('content')
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>

    @php
        $m = fn($v) => 'Rs. ' . number_format((float) $v, fmod((float) $v, 1) ? 2 : 0);
        $statusBadge = [
            'paid' => 'bg-emerald-100 text-emerald-800',
            'partial' => 'bg-amber-100 text-amber-800',
            'unpaid' => 'bg-rose-100 text-rose-800',
            'refunded' => 'bg-gray-200 text-gray-700',
        ];
        $tabs = [
            'all' => ['ALL', 'bg-slate-900 text-white'],
            'paid' => ['PAID', 'bg-emerald-600 text-white'],
            'partial' => ['PARTIAL', 'bg-amber-500 text-white'],
            'unpaid' => ['UNPAID', 'bg-rose-500 text-white'],
            'refunded' => ['REFUNDED', 'bg-gray-500 text-white'],
        ];
        $today = now()->toDateString();
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if (session('success'))
            <div
                class="no-print mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium flex flex-wrap items-center gap-3">
                <span>{{ session('success') }}</span>
                @if (session('print_receipt'))
                    <a target="_blank" href="{{ route('admin.fees.print', session('print_receipt')) }}"
                        class="px-3 py-1 bg-emerald-600 text-white text-xs font-bold rounded-md"><i class="bi bi-printer"></i> Print receipt</a>
                @endif
            </div>
        @endif
        @if ($errors->any())
            <div class="no-print mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-5 gap-4">
            <h2 class="text-xl font-bold text-gray-900"><i class="bi bi-cash-coin"></i> Fees Collection</h2>
            @can('fees.collect')
                <button type="button" id="openCreateBtn"
                    class="no-print px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">+
                    Record Payment</button>
            @endcan
        </div>

        <!-- Stat cards -->
        <div class="no-print grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            @foreach ([['Total collected', $m($stats['collected']), 'bg-emerald-600'], ['Outstanding', $m($stats['outstanding']), 'bg-rose-600'], ['Collected today', $m($stats['today']), 'bg-sky-600'], ['Collected this month', $m($stats['month']), 'bg-indigo-600'], ['Students with dues', $stats['defaulters'], 'bg-slate-900']] as [$label, $value, $color])
                <div class="{{ $color }} text-white p-4 rounded-xl shadow-sm">
                    <p class="text-xl font-bold">{{ $value }}</p>
                    <p class="text-[11px] uppercase tracking-wider opacity-80 font-semibold mt-1">{{ $label }}</p>
                </div>
            @endforeach
        </div>

        <!-- Status tabs -->
        <div class="no-print flex flex-wrap gap-2 mb-5">
            @foreach ($tabs as $key => [$label, $activeClass])
                <a href="{{ route('admin.fees.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition {{ $tab === $key ? $activeClass . ' shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Toolbar -->
            <div class="no-print flex flex-col lg:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.fees.csv', request()->query()) }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-folder2-open"></i>
                        CSV</a>
                    <button type="button" onclick="window.print()" title="Choose 'Save as PDF' in the print dialog"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-filetype-pdf"></i>
                        PDF</button>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-printer"></i>
                        PRINT</button>
                </div>

                <form method="GET" action="{{ route('admin.fees.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <select name="year" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                        @endforeach
                        <option value="all" @selected($year === 'all')>All years</option>
                    </select>

                    <select name="per_page" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page
                            </option>
                        @endforeach
                    </select>

                    <select name="class_id" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All classes</option>
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}" @selected((string) request('class_id') === (string) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>

                    <select name="mode" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All modes</option>
                        @foreach (\App\Models\FeeReceipt::MODES as $v => $l)
                            <option value="{{ $v }}" @selected(request('mode') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="From date">
                    <input type="date" name="to" value="{{ request('to') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="To date">

                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Receipt #, student, admission #"
                        class="text-xs border border-gray-300 rounded-lg p-2 w-52 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">Search</button>

                    @if (request()->hasAny(['search', 'class_id', 'mode', 'from', 'to']))
                        <a href="{{ route('admin.fees.index', ['tab' => $tab, 'year' => $year]) }}"
                            class="px-3 py-2 bg-gray-200 text-xs font-bold rounded-lg">Reset</a>
                    @endif
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Receipt #</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Student</th>
                            <th class="px-4 py-3">Class</th>
                            <th class="px-4 py-3">Due</th>
                            <th class="px-4 py-3">Paid</th>
                            <th class="px-4 py-3">Balance</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($receipts as $r)
                            @php
                                $stu = $r->student;
                                $studentName = \App\Services\ResultService::studentName($stu);
                                $className = $classNames[$stu->school_class_id ?? 0] ?? '—';
                                $adm = \App\Services\ResultService::admission($stu);
                                $last = $r->payments->sortByDesc('id')->first();
                                $modeLabel = $last ? \App\Models\FeeReceipt::MODES[$last->mode] ?? $last->mode : '—';

                                $payload = [
                                    'id' => $r->id,
                                    'receipt_no' => $r->receipt_no,
                                    'date' => $r->receipt_date->format('Y-m-d'),
                                    'academic_year' => $r->academic_year,
                                    'student_name' => $studentName,
                                    'admission' => $adm,
                                    'class_name' => $className,
                                    'subtotal' => $r->subtotal,
                                    'discount' => $r->discount,
                                    'fine' => $r->fine,
                                    'total_due' => $r->total_due,
                                    'paid' => $r->paid_amount,
                                    'balance' => $r->balance,
                                    'status' => $r->status,
                                    'remarks' => $r->remarks,
                                    'refund_reason' => $r->refund_reason,
                                    'refunded_at' => $r->refunded_at?->format('Y-m-d'),
                                    'items' => $r->items
                                        ->map(
                                            fn($i) => [
                                                'description' => $i->description,
                                                'period' => $i->period,
                                                'amount' => $i->amount,
                                            ],
                                        )
                                        ->values(),
                                    'payments' => $r->payments
                                        ->sortBy('id')
                                        ->map(
                                            fn($p) => [
                                                'amount' => $p->amount,
                                                'mode' => \App\Models\FeeReceipt::MODES[$p->mode] ?? $p->mode,
                                                'ref' => $p->reference_no,
                                                'date' => $p->paid_on->format('Y-m-d'),
                                            ],
                                        )
                                        ->values(),
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition" data-receipt="{{ json_encode($payload) }}">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <button type="button"
                                        class="view-btn font-bold text-blue-700 hover:underline font-mono">{{ $r->receipt_no }}</button>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-800">
                                    {{ $r->receipt_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ $studentName }}</div>
                                    <div class="text-[11px] text-gray-400 font-mono">{{ $adm }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $className }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $m($r->total_due) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-bold text-emerald-700">
                                    {{ $m($r->paid_amount) }}</td>
                                <td
                                    class="px-4 py-3 whitespace-nowrap {{ $r->balance > 0 ? 'text-rose-600 font-bold' : 'text-gray-500' }}">
                                    {{ $m($r->balance) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-gray-100 text-gray-700">{{ $modeLabel }}</span>
                                    @if ($r->payments->count() > 1)
                                        <span class="text-[10px] text-gray-400">+{{ $r->payments->count() - 1 }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $statusBadge[$r->status] ?? '' }}">{{ $r->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 no-print">
                                    <button type="button" class="view-btn p-1 hover:text-indigo-600"
                                        title="View details"><i class="bi bi-eye"></i></button>
                                    <a href="{{ route('admin.fees.print', $r) }}" target="_blank"
                                        class="p-1 hover:text-blue-600" title="Print receipt"><i class="bi bi-printer"></i></a>

                                    @can('fees.collect')
                                        @if ($r->balance > 0 && $r->status !== 'refunded')
                                            <button type="button" class="collect-btn p-1 hover:text-emerald-600"
                                                title="Collect balance"><i class="bi bi-cash-coin"></i></button>
                                        @endif
                                    @endcan

                                    @can('fees.refund')
                                        @if ($r->paid_amount > 0 && $r->status !== 'refunded')
                                            <button type="button" class="refund-btn p-1 hover:text-amber-600"
                                                title="Refund">↩</button>
                                        @endif
                                    @endcan

                                    @can('fees.delete')
                                        @if (in_array($r->status, ['unpaid', 'refunded']))
                                            <form action="{{ route('admin.fees.destroy', $r) }}" method="POST"
                                                class="inline" onsubmit="return confirm('Delete this receipt?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="p-1 hover:text-rose-600"
                                                    title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-gray-500">No receipts found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                <div>Showing {{ $receipts->firstItem() ?? 0 }} to {{ $receipts->lastItem() ?? 0 }} of
                    {{ $receipts->total() }} entries</div>
                <div class="no-print">{{ $receipts->links() }}</div>
            </div>
        </div>
    </div>

    <datalist id="periodList">
        @foreach (['Baisakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin', 'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra', 'Term 1', 'Term 2', 'Term 3', 'Annual'] as $p)
            <option value="{{ $p }}">
        @endforeach
    </datalist>

    <!-- Record payment modal -->
    <div id="payModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 max-h-[92vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Record Payment</h3>
                <button type="button" class="close-modal text-xl" data-target="payModal">&times;</button>
            </div>

            <form id="payForm" method="POST" action="{{ route('admin.fees.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="_mode" value="create">

                <!-- Student -->
                <div>
                    <p class="text-[11px] font-bold uppercase text-indigo-600 mb-2">1 · Student</p>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase mb-1">Class *</label>
                            <select id="fClass" required class="w-full text-xs border rounded-lg p-2.5">
                                <option value="">Select class...</option>
                                @foreach ($classes as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase mb-1">Student *</label>
                            <select name="student_id" id="fStudent" required
                                class="w-full text-xs border rounded-lg p-2.5">
                                <option value="">Select class first...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Academic year *</label>
                            <select name="academic_year" required class="w-full text-xs border rounded-lg p-2.5">
                                @foreach ($years as $y)
                                    <option value="{{ $y }}">{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Date *</label>
                            <input type="date" name="receipt_date" required max="{{ $today }}"
                                class="w-full text-xs border rounded-lg p-2.5">
                        </div>
                        <div id="duesBox"
                            class="md:col-span-2 hidden bg-amber-50 border border-amber-300 text-amber-800 text-xs px-3 py-2 rounded-lg font-semibold self-end">
                        </div>
                    </div>
                </div>

                <!-- Items -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <p class="text-[11px] font-bold uppercase text-indigo-600">2 · Fee items</p>
                        <div class="flex gap-2">
                            <select id="periodAll" class="text-xs border rounded-md p-1.5">
                                <option value="">Period for all items…</option>
                                @foreach (\App\Support\AcademicYear::PERIODS as $p)
                                    <option value="{{ $p }}">{{ $p }}</option>
                                @endforeach
                            </select>
                            <button type="button" id="loadFeesBtn"
                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold rounded-md"><i class="bi bi-download"></i>
                                Load class fees</button>
                            <button type="button" id="addItemBtn"
                                class="px-3 py-1.5 bg-gray-200 hover:bg-gray-300 text-[11px] font-bold rounded-md">+ Add
                                item</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-100 text-gray-600 uppercase font-bold">
                                <tr>
                                    <th class="px-2 py-2 text-left w-44">Fee head</th>
                                    <th class="px-2 py-2 text-left">Description</th>
                                    <th class="px-2 py-2 text-left w-32">Period</th>
                                    <th class="px-2 py-2 text-left w-32">Amount (Rs.)</th>
                                    <th class="px-2 py-2 w-8"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Adjustments + payment -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <p class="text-[11px] font-bold uppercase text-indigo-600 mb-2">3 · Discount and fine</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Discount / scholarship</label>
                                <input type="number" step="0.01" min="0" name="discount"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Late fine</label>
                                <input type="number" step="0.01" min="0" name="fine"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                        </div>

                        <p class="text-[11px] font-bold uppercase text-indigo-600 mt-4 mb-2">4 · Payment</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Amount received now</label>
                                <input type="number" step="0.01" min="0" name="paying_now"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Mode</label>
                                <select name="mode" class="w-full text-xs border rounded-lg p-2.5">
                                    @foreach (\App\Models\FeeReceipt::MODES as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-bold uppercase mb-1">Reference no. (cheque, txn id,
                                    voucher)</label>
                                <input type="text" name="reference_no" maxlength="60"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-bold uppercase mb-1">Remarks</label>
                                <input type="text" name="remarks" maxlength="500"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 border rounded-xl p-4 self-start">
                        <p class="text-[11px] font-bold uppercase text-gray-500 mb-3">Summary</p>
                        <dl class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <dt>Fee total</dt>
                                <dd id="sSub" class="font-semibold">Rs. 0</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Discount</dt>
                                <dd id="sDisc" class="font-semibold text-emerald-700">− Rs. 0</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Late fine</dt>
                                <dd id="sFine" class="font-semibold text-rose-700">+ Rs. 0</dd>
                            </div>
                            <div class="flex justify-between border-t pt-2 text-sm">
                                <dt class="font-bold">Total due</dt>
                                <dd id="sTotal" class="font-bold">Rs. 0</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Received now</dt>
                                <dd id="sPay" class="font-semibold text-emerald-700">Rs. 0</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Balance</dt>
                                <dd id="sBal" class="font-semibold">Rs. 0</dd>
                            </div>
                            <div class="flex justify-between items-center">
                                <dt>Status</dt>
                                <dd><span id="sStatus"
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-gray-200 text-gray-700">—</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="payModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save
                        Receipt</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Collect balance modal -->
    <div id="collectModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Collect Balance</h3>
                <button type="button" class="close-modal text-xl" data-target="collectModal">&times;</button>
            </div>
            <form id="collectForm" method="POST" action="" class="space-y-4">
                @csrf
                <p class="text-xs text-gray-600" id="collectWho"></p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Amount *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Date *</label>
                        <input type="date" name="paid_on" required max="{{ $today }}"
                            value="{{ $today }}" class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Mode *</label>
                        <select name="mode" required class="w-full text-xs border rounded-lg p-2.5">
                            @foreach (\App\Models\FeeReceipt::MODES as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Reference no.</label>
                        <input type="text" name="reference_no" maxlength="60"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="collectModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Record
                        Payment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Refund modal -->
    <div id="refundModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Refund Receipt</h3>
                <button type="button" class="close-modal text-xl" data-target="refundModal">&times;</button>
            </div>
            <form id="refundForm" method="POST" action="" class="space-y-4">
                @csrf
                <p class="text-xs text-gray-600" id="refundWho"></p>
                <div class="bg-amber-50 border border-amber-300 text-amber-800 text-xs px-3 py-2 rounded-lg">
                    The whole receipt is marked refunded and no longer counts as money collected. This cannot be undone.
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Reason *</label>
                    <textarea name="reason" rows="2" required maxlength="255"
                        placeholder="e.g. Student transferred, duplicate payment" class="w-full text-xs border rounded-lg p-2.5"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="refundModal">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-amber-600 text-white text-xs font-bold rounded-lg">Refund</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View modal -->
    <div id="viewModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold" id="vTitle">Receipt</h3>
                <button type="button" class="close-modal text-xl" data-target="viewModal">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-4 text-xs mb-4">
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Student</p>
                    <p id="v_student" class="text-gray-900 font-bold"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Class</p>
                    <p id="v_class" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Date</p>
                    <p id="v_date" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Status</p>
                    <p id="v_status" class="text-gray-900 font-bold uppercase"></p>
                </div>
            </div>

            <p class="text-[11px] font-bold uppercase text-gray-500 mb-1">Fee items</p>
            <table class="min-w-full text-xs border rounded-lg overflow-hidden mb-3">
                <thead class="bg-gray-100 text-gray-600 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-left">Period</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody id="vItems" class="divide-y"></tbody>
            </table>

            <div class="text-xs space-y-1 mb-4 ml-auto w-64">
                <div class="flex justify-between"><span>Fee total</span><span id="v_subtotal"></span></div>
                <div class="flex justify-between"><span>Discount</span><span id="v_discount"></span></div>
                <div class="flex justify-between"><span>Late fine</span><span id="v_fine"></span></div>
                <div class="flex justify-between border-t pt-1 font-bold"><span>Total due</span><span
                        id="v_total"></span></div>
                <div class="flex justify-between text-emerald-700 font-bold"><span>Paid</span><span id="v_paid"></span>
                </div>
                <div class="flex justify-between text-rose-600 font-bold"><span>Balance</span><span id="v_balance"></span>
                </div>
            </div>

            <p class="text-[11px] font-bold uppercase text-gray-500 mb-1">Payment history</p>
            <table class="min-w-full text-xs border rounded-lg overflow-hidden mb-3">
                <thead class="bg-gray-100 text-gray-600 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Mode</th>
                        <th class="px-3 py-2 text-left">Reference</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody id="vPayments" class="divide-y"></tbody>
            </table>

            <div id="vNote" class="text-xs text-gray-600 whitespace-pre-line"></div>

            <div class="flex justify-end pt-4 gap-2">
                <a id="vPrint" target="_blank" href="#"
                    class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg"><i class="bi bi-printer"></i> Print receipt</a>
                <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                    data-target="viewModal">Close</button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var STORE_URL = @json(route('admin.fees.store'));
            var BASE_URL = @json(url('/admin/fees'));
            var TODAY = @json($today);
            var DEFAULT_YEAR = @json($defaultYear);
            var STUDENTS = @json($studentsJs);
            var DUES = @json($dues);
            var STRUCTURES = @json($structures);
            var HEADS = @json($heads);

            var form = document.getElementById('payForm');
            var fClass = document.getElementById('fClass');
            var fStudent = document.getElementById('fStudent');
            var itemsBody = document.getElementById('itemsBody');
            var itemIndex = 0;
            var payTouched = false;

            function showModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'flex';
            }

            function hideModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            function num(v) {
                var n = parseFloat(v);
                return isNaN(n) ? 0 : n;
            }

            function round2(n) {
                return Math.round(n * 100) / 100;
            }

            function fmt(n) {
                n = num(n);
                return 'Rs. ' + n.toLocaleString('en-IN', {
                    minimumFractionDigits: (n % 1 ? 2 : 0),
                    maximumFractionDigits: 2
                });
            }

            function mk(tag, props) {
                var e = document.createElement(tag);
                Object.keys(props || {}).forEach(function(k) {
                    e[k] = props[k];
                });
                return e;
            }

            function cell(child) {
                var td = mk('td', {
                    className: 'px-2 py-1.5'
                });
                td.appendChild(child);
                return td;
            }
            var INPUT = 'w-full text-xs border rounded-lg p-2';

            // ---------- items ----------
            function addItem(item) {
                item = item || {};
                var i = itemIndex++;
                var tr = mk('tr', {
                    className: 'item-row'
                });

                var sel = mk('select', {
                    name: 'items[' + i + '][fee_head_id]',
                    className: 'item-head ' + INPUT
                });
                sel.appendChild(mk('option', {
                    value: '',
                    textContent: 'Other / custom'
                }));
                HEADS.forEach(function(h) {
                    sel.appendChild(mk('option', {
                        value: h.id,
                        textContent: h.name
                    }));
                });
                sel.value = item.fee_head_id || '';

                var desc = mk('input', {
                    type: 'text',
                    name: 'items[' + i + '][description]',
                    required: true,
                    maxLength: 150,
                    placeholder: 'Description',
                    className: 'item-desc ' + INPUT,
                    value: item.description || ''
                });
                var period = mk('input', {
                    type: 'text',
                    name: 'items[' + i + '][period]',
                    maxLength: 40,
                    placeholder: 'e.g. Baisakh',
                    className: INPUT,
                    value: item.period || (document.getElementById('periodAll') ? document.getElementById(
                        'periodAll').value : '')
                });
                period.setAttribute('list', 'periodList');
                var amt = mk('input', {
                    type: 'number',
                    step: '0.01',
                    min: '0.01',
                    name: 'items[' + i + '][amount]',
                    required: true,
                    className: 'item-amt ' + INPUT,
                    value: item.amount || ''
                });
                var del = mk('button', {
                    type: 'button',
                    className: 'remove-item px-2 text-rose-600 font-bold',
                    textContent: '×',
                    title: 'Remove'
                });

                tr.appendChild(cell(sel));
                tr.appendChild(cell(desc));
                tr.appendChild(cell(period));
                tr.appendChild(cell(amt));
                tr.appendChild(cell(del));
                itemsBody.appendChild(tr);
            }

            function selectedClassId() {
                return fClass.value;
            }

            function structureFor(headId) {
                var year = form.elements['academic_year'].value.trim();
                return STRUCTURES.filter(function(s) {
                    return String(s.class_id) === String(selectedClassId()) && String(s.head_id) === String(
                        headId) && s.year === year;
                })[0];
            }

            function loadFees() {
                if (!selectedClassId()) {
                    alert('Select a class first.');
                    return;
                }
                var year = form.elements['academic_year'].value.trim();
                var list = STRUCTURES.filter(function(s) {
                    return String(s.class_id) === String(selectedClassId()) && s.year === year;
                });
                if (!list.length) {
                    if (confirm(
                            'No fee structure is set for this class and year yet.\n\nOpen the Fee Structure page to set it up now?'
                            )) {
                        window.location.href = @json(route('admin.fee-structure.index')) + '?year=' + encodeURIComponent(year) +
                            '&class_id=' + encodeURIComponent(selectedClassId());
                    }
                    return;
                }

                var have = {};
                itemsBody.querySelectorAll('.item-head').forEach(function(s) {
                    if (s.value) have[s.value] = true;
                });

                // drop empty placeholder rows before loading
                itemsBody.querySelectorAll('.item-row').forEach(function(tr) {
                    if (!tr.querySelector('.item-head').value && !tr.querySelector('.item-desc').value && !tr
                        .querySelector('.item-amt').value) tr.remove();
                });

                list.forEach(function(s) {
                    if (have[s.head_id]) return;
                    addItem({
                        fee_head_id: s.head_id,
                        description: s.head,
                        amount: s.amount
                    });
                });
                recalc();
            }

            // ---------- students ----------
            function fillStudents(classId, selectedId) {
                fStudent.innerHTML = '';
                fStudent.appendChild(mk('option', {
                    value: '',
                    textContent: classId ? 'Select student...' : 'Select class first...'
                }));
                STUDENTS.filter(function(s) {
                    return String(s.class_id) === String(classId);
                }).forEach(function(s) {
                    fStudent.appendChild(mk('option', {
                        value: s.id,
                        textContent: (s.roll ? s.roll + ' · ' : '') + s.name + (s.adm ? ' (' + s
                            .adm + ')' : '')
                    }));
                });
                if (selectedId) fStudent.value = selectedId;
                showDues();
            }

            function showDues() {
                var box = document.getElementById('duesBox');
                var d = DUES[fStudent.value];
                if (d && d.due > 0) {
                    box.textContent = 'This student already owes ' + fmt(d.due) + ' on ' + d.n +
                        ' receipt(s). Use the collect action on that receipt to collect it.';
                    box.classList.remove('hidden');
                } else {
                    box.classList.add('hidden');
                }
            }

            // ---------- totals ----------
            function recalc() {
                var sub = 0;
                document.querySelectorAll('.item-amt').forEach(function(i) {
                    sub += num(i.value);
                });
                var disc = num(form.elements['discount'].value);
                var fine = num(form.elements['fine'].value);
                var total = Math.max(round2(sub - disc + fine), 0);

                var payInput = form.elements['paying_now'];
                if (!payTouched) payInput.value = total > 0 ? total : '';
                var pay = num(payInput.value);
                payInput.max = total;
                form.elements['mode'].required = pay > 0;

                var bal = Math.max(round2(total - pay), 0);

                document.getElementById('sSub').textContent = fmt(sub);
                document.getElementById('sDisc').textContent = '− ' + fmt(disc);
                document.getElementById('sFine').textContent = '+ ' + fmt(fine);
                document.getElementById('sTotal').textContent = fmt(total);
                document.getElementById('sPay').textContent = fmt(pay);
                document.getElementById('sBal').textContent = fmt(bal);

                var st = document.getElementById('sStatus');
                var cls = 'px-2.5 py-1 rounded-full text-[10px] font-bold uppercase ';
                if (total <= 0) {
                    st.textContent = '—';
                    st.className = cls + 'bg-gray-200 text-gray-700';
                } else if (bal <= 0) {
                    st.textContent = 'Paid';
                    st.className = cls + 'bg-emerald-100 text-emerald-800';
                } else if (pay > 0) {
                    st.textContent = 'Partial';
                    st.className = cls + 'bg-amber-100 text-amber-800';
                } else {
                    st.textContent = 'Unpaid (bill only)';
                    st.className = cls + 'bg-rose-100 text-rose-800';
                }
            }

            function openCreate(old) {
                old = old || {};
                form.reset();
                itemsBody.innerHTML = '';
                itemIndex = 0;
                payTouched = false;

                form.elements['academic_year'].value = old.academic_year || DEFAULT_YEAR;
                form.elements['receipt_date'].value = old.receipt_date || TODAY;

                var stu = STUDENTS.filter(function(s) {
                    return String(s.id) === String(old.student_id);
                })[0];
                fClass.value = stu ? stu.class_id : '';
                fillStudents(fClass.value, old.student_id);

                var items = old.items ? Object.keys(old.items).map(function(k) {
                    return old.items[k];
                }) : [];
                if (items.length) items.forEach(addItem);
                else addItem();

                ['discount', 'fine', 'reference_no', 'remarks'].forEach(function(n) {
                    if (old[n] !== undefined && old[n] !== null) form.elements[n].value = old[n];
                });
                if (old.mode) form.elements['mode'].value = old.mode;
                if (old.paying_now !== undefined && old.paying_now !== null && old.paying_now !== '') {
                    form.elements['paying_now'].value = old.paying_now;
                    payTouched = true;
                }

                recalc();
                showModal('payModal');
            }

            // ---------- other modals ----------
            function rowData(btn) {
                try {
                    return JSON.parse(btn.closest('tr').dataset.receipt);
                } catch (e) {
                    console.error(e);
                    return null;
                }
            }

            function openView(p) {
                function set(id, v) {
                    document.getElementById(id).textContent = (v !== undefined && v !== null && v !== '') ? v : '—';
                }
                set('vTitle', 'Receipt ' + p.receipt_no);
                set('v_student', p.student_name + (p.admission ? ' (' + p.admission + ')' : ''));
                set('v_class', p.class_name);
                set('v_date', p.date);
                set('v_status', p.status);
                set('v_subtotal', fmt(p.subtotal));
                set('v_discount', '− ' + fmt(p.discount));
                set('v_fine', '+ ' + fmt(p.fine));
                set('v_total', fmt(p.total_due));
                set('v_paid', fmt(p.paid));
                set('v_balance', fmt(p.balance));

                var ib = document.getElementById('vItems');
                ib.innerHTML = '';
                p.items.forEach(function(i) {
                    var tr = mk('tr');
                    tr.appendChild(mk('td', {
                        className: 'px-3 py-2',
                        textContent: i.description
                    }));
                    tr.appendChild(mk('td', {
                        className: 'px-3 py-2',
                        textContent: i.period || '—'
                    }));
                    tr.appendChild(mk('td', {
                        className: 'px-3 py-2 text-right',
                        textContent: fmt(i.amount)
                    }));
                    ib.appendChild(tr);
                });

                var pb = document.getElementById('vPayments');
                pb.innerHTML = '';
                if (!p.payments.length) {
                    var e = mk('tr');
                    e.appendChild(mk('td', {
                        colSpan: 4,
                        className: 'px-3 py-3 text-center text-gray-400 italic',
                        textContent: 'No payment received yet.'
                    }));
                    pb.appendChild(e);
                }
                p.payments.forEach(function(x) {
                    var tr = mk('tr');
                    tr.appendChild(mk('td', {
                        className: 'px-3 py-2',
                        textContent: x.date
                    }));
                    tr.appendChild(mk('td', {
                        className: 'px-3 py-2',
                        textContent: x.mode
                    }));
                    tr.appendChild(mk('td', {
                        className: 'px-3 py-2',
                        textContent: x.ref || '—'
                    }));
                    tr.appendChild(mk('td', {
                        className: 'px-3 py-2 text-right',
                        textContent: fmt(x.amount)
                    }));
                    pb.appendChild(tr);
                });

                var note = [];
                if (p.remarks) note.push('Remarks: ' + p.remarks);
                if (p.status === 'refunded') note.push('Refunded on ' + (p.refunded_at || '—') + ' — ' + (p
                    .refund_reason || ''));
                document.getElementById('vNote').textContent = note.join('\n');

                document.getElementById('vPrint').setAttribute('href', BASE_URL + '/' + p.id + '/print');
                showModal('viewModal');
            }

            // ---------- events ----------
            fClass.addEventListener('change', function() {
                fillStudents(fClass.value, null);
            });
            fStudent.addEventListener('change', showDues);
            document.getElementById('addItemBtn').addEventListener('click', function() {
                addItem();
            });
            document.getElementById('loadFeesBtn').addEventListener('click', loadFees);
            document.getElementById('periodAll').addEventListener('change', function() {
                var v = this.value;
                itemsBody.querySelectorAll('input[name$="[period]"]').forEach(function(i) {
                    i.value = v;
                });
            });

            form.addEventListener('input', function(e) {
                if (e.target.name === 'paying_now') payTouched = true;
                recalc();
            });

            form.addEventListener('change', function(e) {
                if (e.target.classList.contains('item-head') && e.target.value) {
                    var tr = e.target.closest('tr');
                    var head = HEADS.filter(function(h) {
                        return String(h.id) === String(e.target.value);
                    })[0];
                    var desc = tr.querySelector('.item-desc'),
                        amt = tr.querySelector('.item-amt');
                    if (head && !desc.value) desc.value = head.name;
                    var s = structureFor(e.target.value);
                    if (s && !amt.value) amt.value = s.amount;
                    recalc();
                }
            });

            document.addEventListener('click', function(e) {
                if (e.target.closest('#openCreateBtn')) {
                    openCreate();
                    return;
                }

                var closeBtn = e.target.closest('.close-modal');
                if (closeBtn) {
                    hideModal(closeBtn.getAttribute('data-target'));
                    return;
                }

                if (['payModal', 'viewModal', 'collectModal', 'refundModal'].indexOf(e.target.id) !== -1) {
                    hideModal(e.target.id);
                    return;
                }

                var rm = e.target.closest('.remove-item');
                if (rm) {
                    if (itemsBody.querySelectorAll('.item-row').length > 1) {
                        rm.closest('tr').remove();
                        recalc();
                    }
                    return;
                }

                var viewBtn = e.target.closest('.view-btn');
                if (viewBtn) {
                    var v = rowData(viewBtn);
                    if (v) openView(v);
                    return;
                }

                var collectBtn = e.target.closest('.collect-btn');
                if (collectBtn) {
                    var c = rowData(collectBtn);
                    if (!c) return;
                    var cf = document.getElementById('collectForm');
                    cf.setAttribute('action', BASE_URL + '/' + c.id + '/pay');
                    cf.elements['amount'].value = c.balance;
                    cf.elements['amount'].max = c.balance;
                    document.getElementById('collectWho').textContent = c.receipt_no + ' · ' + c.student_name +
                        ' · balance ' + fmt(c.balance);
                    showModal('collectModal');
                    return;
                }

                var refundBtn = e.target.closest('.refund-btn');
                if (refundBtn) {
                    var r = rowData(refundBtn);
                    if (!r) return;
                    document.getElementById('refundForm').setAttribute('action', BASE_URL + '/' + r.id +
                        '/refund');
                    document.getElementById('refundWho').textContent = r.receipt_no + ' · ' + r.student_name +
                        ' · paid ' + fmt(r.paid);
                    showModal('refundModal');
                }
            });

            // Reopen the form with the user's input if validation failed
            @if ($errors->any() && old('_mode'))
                openCreate(@json(old()));
            @endif
        })();
    </script>
@endsection
