@extends('layouts.admin')

@section('content')
    @php
        $m = fn($v) => \App\Support\Money::format($v);
        $canEdit = auth()->user()->can('fees.structure');
        $freqLabels = \App\Models\FeeHead::FREQUENCIES;
        $freqBadge = [
            'monthly' => 'bg-sky-100 text-sky-800',
            'termly' => 'bg-violet-100 text-violet-800',
            'yearly' => 'bg-amber-100 text-amber-800',
            'one_time' => 'bg-gray-100 text-gray-700',
        ];
        $today = now()->toDateString();
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('success'))
            <div
                class="mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-5 gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900"><i class="bi bi-tag"></i> Fee Structure</h2>
                <p class="text-xs text-gray-500">The price list per class and year. Fees Collection reads it when you click
                    "Load class fees".</p>
            </div>

            <form method="GET" action="{{ route('admin.fee-structure.index') }}" class="flex flex-wrap items-center gap-2">
                <select name="year" onchange="this.form.submit()"
                    class="text-xs border border-gray-300 rounded-lg p-2.5">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                    @endforeach
                </select>
                <select name="class_id" onchange="this.form.submit()"
                    class="text-xs border border-gray-300 rounded-lg p-2.5">
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if (!$class)
            <div class="bg-white p-6 rounded-xl border border-gray-100 text-sm text-gray-500 text-center">Create a class
                first.</div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

                <!-- Price list -->
                <div class="lg:col-span-2 bg-white shadow-sm rounded-xl p-6 border border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 mb-1">{{ $class->name }} · {{ $year }}</h3>
                    <p class="text-[11px] text-gray-400 mb-4">Leave an amount empty (or 0) if this class does not pay that
                        fee.</p>

                    <form id="priceForm" method="POST" action="{{ route('admin.fee-structure.save') }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="academic_year" value="{{ $year }}">
                        <input type="hidden" name="class_id" value="{{ $class->id }}">

                        <div class="overflow-x-auto rounded-xl border border-gray-200">
                            <table class="min-w-full text-xs">
                                <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Fee head</th>
                                        <th class="px-4 py-3 text-left">Charged</th>
                                        <th class="px-4 py-3 text-left w-48">Amount (Rs.)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach ($heads as $h)
                                        <tr class="{{ $h->is_active ? '' : 'opacity-50' }}">
                                            <td class="px-4 py-2 font-bold text-gray-900">
                                                {{ $h->name }}
                                                @unless ($h->is_active)
                                                    <span class="ml-1 text-[10px] text-gray-400 font-normal">(off)</span>
                                                @endunless
                                            </td>
                                            <td class="px-4 py-2">
                                                <span
                                                    class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $freqBadge[$h->frequency] ?? '' }}">{{ $freqLabels[$h->frequency] ?? $h->frequency }}</span>
                                            </td>
                                            <td class="px-4 py-2">
                                                <input type="number" step="0.01" min="0"
                                                    name="amounts[{{ $h->id }}]"
                                                    value="{{ isset($amounts[$h->id]) ? \App\Services\ResultService::n($amounts[$h->id]) : '' }}"
                                                    data-freq="{{ $h->frequency }}" @disabled(!$canEdit)
                                                    class="amt w-full text-xs border rounded-lg p-2">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mt-4 text-xs">
                            <div class="bg-gray-50 border rounded-lg p-3">
                                <p class="text-gray-500 font-semibold">Regular fees per month</p>
                                <p id="tMonth" class="text-base font-bold text-gray-900">Rs. 0</p>
                            </div>
                            <div class="bg-gray-50 border rounded-lg p-3">
                                <p class="text-gray-500 font-semibold">Estimated for the year</p>
                                <p id="tYear" class="text-base font-bold text-gray-900">Rs. 0</p>
                                <p class="text-[10px] text-gray-400">12 months · 3 terms</p>
                            </div>
                        </div>

                        @if ($canEdit)
                            <div class="flex justify-end mt-4">
                                <button type="submit"
                                    class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow"><i class="bi bi-save"></i>
                                    Save fee structure</button>
                            </div>
                        @endif
                    </form>
                </div>

                <!-- Side panels -->
                <div class="space-y-5">

                    @can('fees.generate')
                        <div class="bg-white shadow-sm rounded-xl p-5 border border-gray-100">
                            <h3 class="text-sm font-bold text-gray-900 mb-1"><i class="bi bi-receipt"></i> Generate bills</h3>
                            <p class="text-[11px] text-gray-400 mb-3">
                                Creates one unpaid receipt for each of the {{ $studentCounts[$class->id] ?? 0 }} student(s) in
                                {{ $class->name }}.
                                Students already billed for that period are skipped.
                            </p>

                            <form method="POST" action="{{ route('admin.fee-structure.generate') }}"
                                onsubmit="return confirm('Create bills for {{ $studentCounts[$class->id] ?? 0 }} student(s) in {{ $class->name }}?');"
                                class="space-y-3">
                                @csrf
                                <input type="hidden" name="academic_year" value="{{ $year }}">
                                <input type="hidden" name="class_id" value="{{ $class->id }}">

                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Period *</label>
                                    <select name="period" required class="w-full text-xs border rounded-lg p-2.5">
                                        <option value="">Select period...</option>
                                        @foreach (\App\Support\AcademicYear::PERIODS as $p)
                                            <option value="{{ $p }}">{{ $p }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Bill date *</label>
                                    <input type="date" name="receipt_date" required max="{{ $today }}"
                                        value="{{ $today }}" class="w-full text-xs border rounded-lg p-2.5">
                                </div>

                                <div>
                                    <p class="block text-xs font-bold uppercase mb-1">Fee heads to bill</p>
                                    @php $billable = $heads->filter(fn ($h) => $h->is_active && ($amounts[$h->id] ?? 0) > 0); @endphp
                                    @forelse ($billable as $h)
                                        <label class="flex items-center justify-between gap-2 text-xs py-1">
                                            <span class="flex items-center gap-2">
                                                <input type="checkbox" name="head_ids[]" value="{{ $h->id }}"
                                                    class="rounded border-gray-300" @checked($h->frequency === 'monthly')>
                                                {{ $h->name }}
                                            </span>
                                            <span class="text-gray-500">{{ $m($amounts[$h->id]) }}</span>
                                        </label>
                                    @empty
                                        <p class="text-[11px] text-amber-600">Save the amounts in the price list first.</p>
                                    @endforelse
                                </div>

                                <button type="submit" @disabled($billable->isEmpty())
                                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white text-xs font-bold rounded-lg shadow">Generate
                                    bills</button>
                            </form>
                        </div>
                    @endcan

                    @if ($canEdit)
                        <details class="bg-white shadow-sm rounded-xl p-5 border border-gray-100">
                            <summary class="text-sm font-bold text-gray-900 cursor-pointer"><i class="bi bi-clipboard"></i> Copy this price list
                            </summary>
                            <form method="POST" action="{{ route('admin.fee-structure.copy') }}"
                                class="space-y-3 mt-3">
                                @csrf
                                <input type="hidden" name="from_year" value="{{ $year }}">
                                <input type="hidden" name="from_class_id" value="{{ $class->id }}">

                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Copy to year</label>
                                    <select name="to_year" class="w-full text-xs border rounded-lg p-2.5">
                                        @foreach ($years as $y)
                                            <option value="{{ $y }}" @selected($year === $y)>
                                                {{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Copy to classes</label>
                                    <div
                                        class="grid grid-cols-1 gap-1 max-h-36 overflow-y-auto border rounded-lg p-2 text-xs">
                                        @foreach ($classes as $c)
                                            <label class="flex items-center gap-2"><input type="checkbox"
                                                    name="target_ids[]" value="{{ $c->id }}"
                                                    class="rounded border-gray-300"> {{ $c->name }}</label>
                                        @endforeach
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase mb-1">Increase by %</label>
                                    <input type="number" step="0.1" min="0" max="100"
                                        name="increase_percent" placeholder="e.g. 10 for a yearly rise"
                                        class="w-full text-xs border rounded-lg p-2.5">
                                </div>

                                <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="overwrite"
                                        value="1" class="rounded border-gray-300"> Overwrite amounts that already
                                    exist</label>

                                <button type="submit"
                                    class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg">Copy</button>
                            </form>
                        </details>
                    @endif
                </div>
            </div>

            <!-- Overview -->
            <div class="bg-white shadow-sm rounded-xl p-6 border border-gray-100 mb-5">
                <h3 class="text-sm font-bold text-gray-900 mb-3">All classes · {{ $year }}</h3>
                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-100 text-gray-600 uppercase font-bold">
                            <tr>
                                <th class="px-4 py-2 text-left">Class</th>
                                <th class="px-4 py-2 text-left">Students</th>
                                <th class="px-4 py-2 text-left">Fee heads set</th>
                                <th class="px-4 py-2 text-left">Sum of all heads</th>
                                <th class="px-4 py-2 text-left">Status</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($classes as $c)
                                @php $o = $overview[$c->id] ?? null; @endphp
                                <tr>
                                    <td class="px-4 py-2 font-bold text-gray-900">{{ $c->name }}</td>
                                    <td class="px-4 py-2">{{ $studentCounts[$c->id] ?? 0 }}</td>
                                    <td class="px-4 py-2">{{ $o->heads ?? 0 }}</td>
                                    <td class="px-4 py-2">{{ $o ? $m($o->total) : '—' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($o)
                                            <span
                                                class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">Set</span>
                                        @else
                                            <span
                                                class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-rose-100 text-rose-800">Not
                                                set</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="{{ route('admin.fee-structure.index', ['year' => $year, 'class_id' => $c->id]) }}"
                                            class="text-indigo-600 font-bold hover:underline">Open</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Fee heads -->
        @if ($canEdit)
            <div class="bg-white shadow-sm rounded-xl p-6 border border-gray-100">
                <h3 class="text-sm font-bold text-gray-900 mb-1">Fee heads</h3>
                <p class="text-[11px] text-gray-400 mb-3">Rename, change how often a fee is charged, or switch a head off.
                    Heads already used in receipts cannot be deleted, only switched off.</p>

                @foreach ($heads as $h)
                    <form method="POST" action="{{ route('admin.fee-structure.heads.update', $h) }}"
                        class="flex flex-wrap items-center gap-2 py-2 border-b last:border-0">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $h->name }}" required maxlength="80"
                            class="text-xs border rounded-lg p-2 w-56">
                        <select name="frequency" class="text-xs border rounded-lg p-2">
                            @foreach ($freqLabels as $v => $l)
                                <option value="{{ $v }}" @selected($h->frequency === $v)>{{ $l }}
                                </option>
                            @endforeach
                        </select>
                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active"
                                value="1" class="rounded border-gray-300" @checked($h->is_active)>
                            Active</label>
                        <button type="submit"
                            class="px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md">Save</button>
                        @unless ($usedHeadIds->contains($h->id))
                            <button type="submit" form="delhead-{{ $h->id }}" class="px-2 text-rose-600"
                                title="Delete" onclick="return confirm('Delete this fee head?');"><i class="bi bi-trash"></i></button>
                        @endunless
                    </form>
                @endforeach

                @foreach ($heads as $h)
                    @unless ($usedHeadIds->contains($h->id))
                        <form id="delhead-{{ $h->id }}" method="POST"
                            action="{{ route('admin.fee-structure.heads.destroy', $h) }}" class="hidden">@csrf
                            @method('DELETE')</form>
                    @endunless
                @endforeach

                <form method="POST" action="{{ route('admin.fee-structure.heads.store') }}"
                    class="flex flex-wrap items-center gap-2 pt-4">
                    @csrf
                    <input type="text" name="name" required maxlength="80"
                        placeholder="New fee head, e.g. Science Lab Fee" class="text-xs border rounded-lg p-2 w-64">
                    <select name="frequency" class="text-xs border rounded-lg p-2">
                        @foreach ($freqLabels as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </select>
                    <button type="submit"
                        class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-md">+
                        Add fee head</button>
                </form>
            </div>
        @endif
    </div>

    <script>
        (function() {
            function money(n) {
                return 'Rs. ' + n.toLocaleString('en-IN', {
                    maximumFractionDigits: 2
                });
            }

            function calc() {
                var t = {
                    monthly: 0,
                    termly: 0,
                    yearly: 0,
                    one_time: 0
                };
                document.querySelectorAll('.amt').forEach(function(i) {
                    var v = parseFloat(i.value) || 0;
                    if (t[i.dataset.freq] !== undefined) t[i.dataset.freq] += v;
                });
                var year = t.monthly * 12 + t.termly * 3 + t.yearly + t.one_time;
                document.getElementById('tMonth').textContent = money(t.monthly);
                document.getElementById('tYear').textContent = money(year);
            }

            var form = document.getElementById('priceForm');
            if (form) {
                form.addEventListener('input', calc);
                calc();
            }
        })();
    </script>
@endsection
