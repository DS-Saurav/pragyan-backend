@extends('layouts.admin')

@section('content')
    <style>
        @media print { .no-print { display: none !important; } }
    </style>

    @php
        $m = fn ($v) => \App\Support\Money::format($v);
        $today = now()->toDateString();
        $tabs = [
            'all' => 'All',
            'income' => 'Income',
            'expense' => 'Expense',
        ];
        $schoolYearStart = (now()->month >= 4 ? now()->year : now()->year - 1) . '-04-01';
        $quick = [
            'Today' => [$today, $today],
            'This month' => [now()->startOfMonth()->toDateString(), $today],
            'This school year' => [$schoolYearStart, $today],
        ];
        $sourceBadge = ['fees' => 'bg-emerald-50 text-emerald-700', 'refund' => 'bg-amber-50 text-amber-700', 'payroll' => 'bg-violet-50 text-violet-700'];
        $canManage = auth()->user()->can('accounts.manage');
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if (session('success'))
            <div class="no-print mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="no-print mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <x-ui.page-header title="Daily Accounts"
            subtitle="{{ \Carbon\Carbon::parse($from)->format('M j, Y') }} to {{ \Carbon\Carbon::parse($to)->format('M j, Y') }}. Fees, refunds and salaries appear automatically. Add everything else here.">
            <x-slot name="actions">
                @if ($canManage)
                    <button type="button" id="openCreateBtn" class="no-print ui-btn ui-btn-primary">
                        <i class="bi bi-plus-lg"></i> Add entry
                    </button>
                @endif
            </x-slot>
        </x-ui.page-header>

        <div class="no-print grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="ui-stat">
                <div>
                    <p class="ui-stat-label">Total income</p>
                    <p class="ui-stat-value text-emerald-700">{{ $m($stats['income']) }}</p>
                </div>
                <span class="ui-stat-icon bg-emerald-50 text-emerald-700"><i class="bi bi-arrow-down-left"></i></span>
            </div>
            <div class="ui-stat">
                <div>
                    <p class="ui-stat-label">Total expense</p>
                    <p class="ui-stat-value text-rose-700">{{ $m($stats['expense']) }}</p>
                </div>
                <span class="ui-stat-icon bg-rose-50 text-rose-700"><i class="bi bi-arrow-up-right"></i></span>
            </div>
            <div class="ui-stat">
                <div>
                    <p class="ui-stat-label">{{ $stats['net'] < 0 ? 'Deficit' : 'Surplus' }} this period</p>
                    <p class="ui-stat-value {{ $stats['net'] < 0 ? 'text-rose-700' : 'text-sky-700' }}">
                        {{ $stats['net'] < 0 ? '−' : '' }}{{ $m(abs($stats['net'])) }}
                    </p>
                </div>
                <span class="ui-stat-icon bg-sky-50 text-sky-700"><i class="bi bi-graph-up"></i></span>
            </div>
            <div class="ui-stat">
                <div>
                    @if ($stats['narrowed'])
                        <p class="ui-stat-label">Closing balance</p>
                        <p class="text-sm font-semibold text-slate-700">Balance hidden</p>
                        <p class="mt-1 text-[11px] text-slate-500">Clear filters to see opening and closing balance.</p>
                    @else
                        <p class="ui-stat-label">Closing balance</p>
                        <p class="ui-stat-value {{ $stats['closing'] < 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                            {{ $stats['closing'] < 0 ? '−' : '' }}{{ $m(abs($stats['closing'])) }}
                        </p>
                        <p class="mt-1 text-[10px] text-slate-500">
                            Opening {{ $m($stats['opening']) }} · Cash {{ $m($stats['cash']) }} · Digital {{ $m($stats['digital']) }}
                        </p>
                    @endif
                </div>
                <span class="ui-stat-icon bg-slate-100 text-slate-700"><i class="bi bi-bank"></i></span>
            </div>
        </div>

        <div class="no-print ui-tabs">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.accounts.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                    @class(['ui-tab', 'ui-tab-active' => $tab === $key])>
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <div class="no-print flex flex-col xl:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.accounts.csv', request()->query()) }}" class="ui-btn ui-btn-secondary ui-btn-sm">
                        <i class="bi bi-filetype-csv"></i> CSV
                    </a>
                    <button type="button" onclick="window.print()" class="ui-btn ui-btn-secondary ui-btn-sm">
                        <i class="bi bi-printer"></i> Print / PDF
                    </button>
                    <a target="_blank" href="{{ route('admin.accounts.book', ['from' => $from, 'to' => $to]) }}" class="ui-btn ui-btn-secondary ui-btn-sm">
                        <i class="bi bi-journal-text"></i> Day book
                    </a>
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex flex-wrap gap-1">
                        @foreach ($quick as $label => [$a, $b])
                            <a href="{{ route('admin.accounts.index', ['from' => $a, 'to' => $b, 'tab' => $tab]) }}"
                                class="px-2.5 py-1 rounded-md text-[11px] font-bold {{ $from === $a && $to === $b ? 'bg-indigo-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' }}">{{ $label }}</a>
                        @endforeach
                    </div>

                    <form method="GET" action="{{ route('admin.accounts.index') }}" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <input type="date" name="from" value="{{ $from }}" max="{{ $today }}" onchange="this.form.submit()" class="text-xs border border-gray-300 rounded-lg p-2" title="From">
                        <input type="date" name="to" value="{{ $to }}" max="{{ $today }}" onchange="this.form.submit()" class="text-xs border border-gray-300 rounded-lg p-2" title="To">

                        <select name="category" onchange="this.form.submit()" class="text-xs border border-gray-300 rounded-lg p-2">
                            <option value="">All categories</option>
                            @foreach ($categories as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>@endforeach
                        </select>

                        <select name="mode" onchange="this.form.submit()" class="text-xs border border-gray-300 rounded-lg p-2">
                            <option value="">All modes</option>
                            @foreach (\App\Support\Money::MODES as $v => $l)<option value="{{ $v }}" @selected(request('mode') === $v)>{{ $l }}</option>@endforeach
                        </select>

                        <select name="per_page" onchange="this.form.submit()" class="text-xs border border-gray-300 rounded-lg p-2">
                            @foreach ([10, 25, 50, 100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page</option>@endforeach
                        </select>

                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search description..." class="text-xs border border-gray-300 rounded-lg p-2 w-44">
                        <button type="submit" class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg">Search</button>
                        @if (request()->hasAny(['category', 'mode', 'search']))
                            <a href="{{ route('admin.accounts.index', ['from' => $from, 'to' => $to, 'tab' => $tab]) }}" class="px-3 py-2 bg-gray-200 text-xs font-bold rounded-lg">Reset</a>
                        @endif
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3">Mode</th>
                            <th class="px-4 py-3">Recorded by</th>
                            <th class="px-4 py-3 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($entries as $e)
                            <tr class="hover:bg-gray-50/50" @if ($e['entry']) data-entry="{{ json_encode($e['entry']) }}" @endif>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $e['date']->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $e['type'] === 'income' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ $e['type'] }}</span>
                                </td>
                                <td class="px-4 py-3"><span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase bg-gray-100 text-gray-700">{{ $e['category'] }}</span></td>
                                <td class="px-4 py-3 text-gray-800">{{ $e['description'] }}@if ($e['ref'])<span class="text-[11px] text-gray-400"> · Ref {{ $e['ref'] }}</span>@endif</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-bold {{ $e['type'] === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $e['type'] === 'income' ? '+' : '−' }}{{ $m($e['amount']) }}</td>
                                <td class="px-4 py-3"><span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-gray-100 text-gray-700">{{ \App\Support\Money::MODES[$e['mode']] ?? $e['mode'] }}</span></td>
                                <td class="px-4 py-3 text-gray-600">{{ $e['by'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap no-print">
                                    @if ($e['entry'] && $canManage)
                                        <button type="button" class="edit-btn inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-amber-50 hover:text-amber-600" title="Edit" aria-label="Edit entry">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('admin.accounts.destroy', $e['entry']['id']) }}" method="POST" class="inline" onsubmit="return confirm('Delete this entry?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Delete" aria-label="Delete entry">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @elseif ($e['link'])
                                        <a href="{{ $e['link'] }}" target="_blank" class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ $sourceBadge[$e['source']] ?? 'bg-gray-100 text-gray-600' }}" title="Created automatically. Open the source.">Auto · {{ ucfirst($e['source']) }} ↗</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No entries in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                <div>Showing {{ $entries->firstItem() ?? 0 }} to {{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} entries</div>
                <div class="no-print">{{ $entries->links() }}</div>
            </div>
        </div>
    </div>

    @if ($canManage)
        <div id="entryModal" style="display:none;" class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 max-h-[92vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <h3 class="text-md font-bold" id="formTitle">Add Entry</h3>
                    <button type="button" class="close-modal text-xl" data-target="entryModal">&times;</button>
                </div>

                <form id="entryForm" method="POST" action="{{ route('admin.accounts.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="PUT" disabled>
                    <input type="hidden" name="_mode" id="formMode" value="create">
                    <input type="hidden" name="_edit_id" id="formEditId" value="">

                    <div class="flex gap-2">
                        <label class="flex-1 cursor-pointer"><input type="radio" name="type" value="income" class="peer hidden" checked>
                            <span class="block text-center text-xs font-bold py-2 rounded-lg border peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600">+ Income</span></label>
                        <label class="flex-1 cursor-pointer"><input type="radio" name="type" value="expense" class="peer hidden">
                            <span class="block text-center text-xs font-bold py-2 rounded-lg border peer-checked:bg-rose-600 peer-checked:text-white peer-checked:border-rose-600">− Expense</span></label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Date *</label>
                            <input type="date" name="entry_date" required max="{{ $today }}" class="w-full text-xs border rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Amount (Rs.) *</label>
                            <input type="number" step="0.01" min="0.01" name="amount" required class="w-full text-xs border rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Category *</label>
                            <select name="category" id="fCategory" required class="w-full text-xs border rounded-lg p-2.5"></select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Mode *</label>
                            <select name="mode" required class="w-full text-xs border rounded-lg p-2.5">
                                @foreach (\App\Support\Money::MODES as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold uppercase mb-1">Description *</label>
                            <input type="text" name="description" required maxlength="255" placeholder="e.g. Whiteboard markers x 40" class="w-full text-xs border rounded-lg p-2.5">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold uppercase mb-1">Reference no. (bill, voucher)</label>
                            <input type="text" name="reference_no" maxlength="60" class="w-full text-xs border rounded-lg p-2.5">
                        </div>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg" data-target="entryModal">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save Entry</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function () {
                var STORE_URL = @json(route('admin.accounts.store'));
                var BASE_URL = @json(url('/admin/accounts'));
                var TODAY = @json($today);
                var CATS = { income: @json(\App\Models\AccountEntry::INCOME_CATEGORIES), expense: @json(\App\Models\AccountEntry::EXPENSE_CATEGORIES) };

                var form = document.getElementById('entryForm');
                var cat = document.getElementById('fCategory');

                function showModal(id) { var el = document.getElementById(id); if (el) el.style.display = 'flex'; }
                function hideModal(id) { var el = document.getElementById(id); if (el) el.style.display = 'none'; }

                function currentType() { return form.querySelector('input[name="type"]:checked').value; }

                function fillCategories(selected) {
                    cat.innerHTML = '';
                    CATS[currentType()].forEach(function (c) {
                        var o = document.createElement('option'); o.value = c; o.textContent = c; cat.appendChild(o);
                    });
                    if (selected && CATS[currentType()].indexOf(selected) !== -1) cat.value = selected;
                }

                function openForm(mode, data, id) {
                    data = data || {};
                    var edit = mode === 'edit';

                    form.reset();
                    document.getElementById('formTitle').textContent = edit ? 'Edit Entry' : 'Add Entry';
                    form.setAttribute('action', edit ? BASE_URL + '/' + id : STORE_URL);
                    document.getElementById('formMethod').disabled = !edit;
                    document.getElementById('formMode').value = mode;
                    document.getElementById('formEditId').value = id || '';

                    form.querySelector('input[name="type"][value="' + (data.type === 'expense' ? 'expense' : 'income') + '"]').checked = true;
                    fillCategories(data.category);

                    form.elements['entry_date'].value = data.entry_date || TODAY;
                    form.elements['amount'].value = data.amount !== undefined ? data.amount : '';
                    form.elements['description'].value = data.description || '';
                    form.elements['mode'].value = data.mode || 'cash';
                    form.elements['reference_no'].value = data.reference_no || '';

                    showModal('entryModal');
                }

                form.querySelectorAll('input[name="type"]').forEach(function (r) {
                    r.addEventListener('change', function () { fillCategories(null); });
                });

                document.addEventListener('click', function (e) {
                    if (e.target.closest('#openCreateBtn')) { openForm('create', {}, null); return; }

                    var closeBtn = e.target.closest('.close-modal');
                    if (closeBtn) { hideModal(closeBtn.getAttribute('data-target')); return; }
                    if (e.target.id === 'entryModal') { hideModal('entryModal'); return; }

                    var editBtn = e.target.closest('.edit-btn');
                    if (editBtn) {
                        try { var d = JSON.parse(editBtn.closest('tr').dataset.entry); openForm('edit', d, d.id); }
                        catch (err) { console.error(err); }
                    }
                });

                @if ($errors->any() && old('_mode'))
                    var OLD = @json(old());
                    openForm(OLD._mode === 'edit' ? 'edit' : 'create', OLD, OLD._edit_id || null);
                @endif
            })();
        </script>
    @endif
@endsection