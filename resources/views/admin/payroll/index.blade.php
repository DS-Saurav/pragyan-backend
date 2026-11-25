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
        $m = fn($v) => \App\Support\Money::format($v);
        $badge = ['generated' => 'bg-amber-100 text-amber-800', 'paid' => 'bg-emerald-100 text-emerald-800'];
        $tabs = [
            'all' => ['ALL', 'bg-slate-900 text-white'],
            'generated' => ['GENERATED', 'bg-amber-500 text-white'],
            'paid' => ['PAID', 'bg-emerald-600 text-white'],
        ];
        $today = now()->toDateString();
        $thisMonth = now()->format('Y-m');
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if (session('success'))
            <div
                class="no-print mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium flex flex-wrap items-center gap-3">
                <span>{{ session('success') }}</span>
                @if (session('print_slip'))
                    <a target="_blank" href="{{ route('admin.payroll.slip', session('print_slip')) }}"
                        class="px-3 py-1 bg-emerald-600 text-white text-xs font-bold rounded-md"><i class="bi bi-printer"></i> Print slip</a>
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

        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-5 gap-4">
            <h2 class="text-xl font-bold text-gray-900"><i class="bi bi-cash-stack"></i> Payroll / Salary Slips</h2>
            <div class="no-print flex flex-wrap gap-2">
                <a href="{{ route('admin.payroll.staff.index') }}"
                    class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-xs font-bold rounded-lg"><i class="bi bi-people"></i> Staff
                    &amp; salary setup</a>
                @can('payroll.manage')
                    <button type="button" id="openRunBtn"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow"><i class="bi bi-lightning"></i> Run
                        payroll</button>
                    <button type="button" id="openCreateBtn"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">+
                        Generate Slip</button>
                @endcan
            </div>
        </div>

        <!-- Stat cards -->
        <div class="no-print grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            @foreach ([['Total slips', $stats['slips'], 'bg-slate-900'], ['Paid', $stats['paid'], 'bg-emerald-600'], ['Pending payment', $stats['pending_count'] . ' · ' . $m($stats['pending_amount']), 'bg-amber-600'], ['Total disbursed', $m($stats['disbursed']), 'bg-sky-600']] as [$label, $value, $color])
                <div class="{{ $color }} text-white p-4 rounded-xl shadow-sm">
                    <p class="text-xl font-bold">{{ $value }}</p>
                    <p class="text-[11px] uppercase tracking-wider opacity-80 font-semibold mt-1">{{ $label }}</p>
                </div>
            @endforeach
        </div>

        <!-- Status tabs -->
        <div class="no-print flex flex-wrap gap-2 mb-5">
            @foreach ($tabs as $key => [$label, $activeClass])
                <a href="{{ route('admin.payroll.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition {{ $tab === $key ? $activeClass . ' shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Toolbar -->
            <div class="no-print flex flex-col lg:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.payroll.csv', request()->query()) }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-folder2-open"></i>
                        CSV</a>
                    <button type="button" onclick="window.print()" title="Choose 'Save as PDF' in the print dialog"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-filetype-pdf"></i>
                        PDF</button>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-printer"></i>
                        PRINT</button>
                    @if ($month !== 'all' && $slips->total() > 0)
                        <a target="_blank" href="{{ route('admin.payroll.print-month', ['month' => $month]) }}"
                            class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-filetype-pdf"></i>
                            All slips of month</a>
                    @endif
                    @can('payroll.pay')
                        @if ($month !== 'all' && $stats['pending_count'] > 0)
                            <button type="button" id="openPayAllBtn"
                                class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-cash-coin"></i>
                                Mark all paid ({{ $stats['pending_count'] }})</button>
                        @endif
                    @endcan
                </div>

                <form method="GET" action="{{ route('admin.payroll.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <select name="month" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="all" @selected($month === 'all')>All months</option>
                        @foreach ($monthOptions as $value => $label)
                            <option value="{{ $value }}" @selected($month === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <select name="type" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All staff</option>
                        @foreach (\App\Models\StaffMember::TYPES as $v => $l)
                            <option value="{{ $v }}" @selected(request('type') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>

                    <select name="per_page" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page
                            </option>
                        @endforeach
                    </select>

                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Staff, slip no..."
                        class="text-xs border border-gray-300 rounded-lg p-2 w-40 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">Search</button>

                    @if (request()->hasAny(['search', 'type']))
                        <a href="{{ route('admin.payroll.index', ['tab' => $tab, 'month' => $month]) }}"
                            class="px-3 py-2 bg-gray-200 text-xs font-bold rounded-lg">Reset</a>
                    @endif
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Slip No</th>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3">Basic</th>
                            <th class="px-4 py-3">Net Pay</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($slips as $s)
                            @php
                                $payload = [
                                    'id' => $s->id,
                                    'slip_no' => $s->slip_no,
                                    'staff_member_id' => $s->staff_member_id,
                                    'staff_name' => $s->staff_name,
                                    'designation' => $s->designation,
                                    'month' => $s->month,
                                    'month_label' => \App\Models\SalarySlip::monthLabel($s->month),
                                    'basic' => $s->basic,
                                    'working_days' => $s->working_days,
                                    'unpaid_days' => $s->unpaid_days,
                                    'gross' => $s->gross,
                                    'total_deductions' => $s->total_deductions,
                                    'net_pay' => $s->net_pay,
                                    'status' => $s->status,
                                    'paid_on' => $s->paid_on?->format('Y-m-d'),
                                    'mode_label' => \App\Support\Money::MODES[$s->payment_mode] ?? '',
                                    'reference_no' => $s->reference_no,
                                    'remarks' => $s->remarks,
                                    'earnings' => $s->lines
                                        ->where('type', 'earning')
                                        ->map(fn($l) => ['name' => $l->name, 'amount' => $l->amount])
                                        ->values(),
                                    'deductions' => $s->lines
                                        ->where('type', 'deduction')
                                        ->map(fn($l) => ['name' => $l->name, 'amount' => $l->amount])
                                        ->values(),
                                    'edit_deductions' => $s->lines
                                        ->where('type', 'deduction')
                                        ->where('is_system', false)
                                        ->map(fn($l) => ['name' => $l->name, 'amount' => $l->amount])
                                        ->values(),
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition" data-slip="{{ json_encode($payload) }}">
                                <td class="px-4 py-3 whitespace-nowrap font-mono">
                                    <button type="button"
                                        class="view-btn font-bold text-blue-700 hover:underline">{{ $s->slip_no }}</button>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ $s->staff_name }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $s->designation ?: '—' }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $payload['month_label'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $m($s->basic) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-900">{{ $m($s->net_pay) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $badge[$s->status] ?? '' }}">{{ $s->status }}</span>
                                    @if ($s->paid_on)
                                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $s->paid_on->format('Y-m-d') }} ·
                                            {{ $payload['mode_label'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 no-print">
                                    <button type="button" class="view-btn p-1 hover:text-indigo-600"
                                        title="View"><i class="bi bi-eye"></i></button>
                                    <a href="{{ route('admin.payroll.slip', $s) }}" target="_blank"
                                        class="p-1 hover:text-blue-600" title="Print slip"><i class="bi bi-printer"></i></a>

                                    @if ($s->status === 'generated')
                                        @can('payroll.manage')
                                            <button type="button" class="edit-btn p-1 hover:text-indigo-600"
                                                title="Edit"><i class="bi bi-pencil"></i></button>
                                        @endcan
                                        @can('payroll.pay')
                                            <button type="button" class="pay-btn p-1 hover:text-emerald-600"
                                                title="Mark as paid"><i class="bi bi-cash-coin"></i></button>
                                        @endcan
                                        @can('payroll.manage')
                                            <form action="{{ route('admin.payroll.destroy', $s) }}" method="POST"
                                                class="inline" onsubmit="return confirm('Delete this salary slip?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="p-1 hover:text-rose-600"
                                                    title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    @else
                                        @can('payroll.pay')
                                            <form action="{{ route('admin.payroll.unpay', $s) }}" method="POST"
                                                class="inline"
                                                onsubmit="return confirm('Undo this payment? The slip goes back to Generated.');">
                                                @csrf
                                                <button type="submit" class="p-1 hover:text-amber-600"
                                                    title="Undo payment">↩</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No salary slips found. Use
                                    "Run payroll" to generate them for a month.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                <div>Showing {{ $slips->firstItem() ?? 0 }} to {{ $slips->lastItem() ?? 0 }} of {{ $slips->total() }}
                    entries</div>
                <div class="no-print">{{ $slips->links() }}</div>
            </div>
        </div>
    </div>

    <datalist id="compList">
        @foreach ($componentNames as $n)
            <option value="{{ $n }}">
        @endforeach
    </datalist>

    <!-- Generate / edit slip -->
    <div id="slipModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 max-h-[92vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold" id="formTitle">Generate Salary Slip</h3>
                <button type="button" class="close-modal text-xl" data-target="slipModal">&times;</button>
            </div>

            <form id="slipForm" method="POST" action="{{ route('admin.payroll.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="PUT" disabled>
                <input type="hidden" name="_mode" id="formMode" value="create">
                <input type="hidden" name="_edit_id" id="formEditId" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Staff member *</label>
                        <select name="staff_member_id" required class="w-full text-xs border rounded-lg p-2.5">
                            <option value="">— Select staff —</option>
                            @foreach ($activeStaff as $st)
                                <option value="{{ $st->id }}">
                                    {{ $st->name }}{{ $st->designation ? ' · ' . $st->designation : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Salary month *</label>
                        <input type="month" name="month" required class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Basic salary *</label>
                        <input type="number" step="0.01" min="0" name="basic" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Working days</label>
                            <input type="number" min="1" max="31" name="working_days" required
                                class="w-full text-xs border rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Unpaid leave days</label>
                            <input type="number" step="0.5" min="0" max="31" name="unpaid_days"
                                class="w-full text-xs border rounded-lg p-2.5">
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <p class="text-xs font-bold uppercase text-emerald-700">Allowances</p>
                        <button type="button"
                            class="add-row px-3 py-1 bg-gray-100 hover:bg-gray-200 text-[11px] font-bold rounded-md"
                            data-kind="earnings">+ Add allowance</button>
                    </div>
                    <div id="earnBox"></div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <p class="text-xs font-bold uppercase text-rose-700">Deductions</p>
                        <button type="button"
                            class="add-row px-3 py-1 bg-gray-100 hover:bg-gray-200 text-[11px] font-bold rounded-md"
                            data-kind="deductions">+ Add deduction</button>
                    </div>
                    <div id="dedBox"></div>
                    <p class="text-[11px] text-gray-400">Unpaid leave is deducted automatically from the gross pay: gross ÷
                        working days × unpaid days.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Remarks</label>
                    <input type="text" name="remarks" maxlength="500" class="w-full text-xs border rounded-lg p-2.5">
                </div>

                <div class="bg-gray-50 border rounded-xl p-4 text-xs space-y-1">
                    <div class="flex justify-between"><span>Gross pay</span><span id="sGross"
                            class="font-semibold">Rs. 0</span></div>
                    <div class="flex justify-between"><span>Unpaid leave deduction</span><span id="sUnpaid"
                            class="font-semibold text-rose-700">Rs. 0</span></div>
                    <div class="flex justify-between"><span>Other deductions</span><span id="sDed"
                            class="font-semibold text-rose-700">Rs. 0</span></div>
                    <div class="flex justify-between border-t pt-2 text-sm"><span class="font-bold">Net pay</span><span
                            id="sNet" class="font-bold text-emerald-700">Rs. 0</span></div>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="slipModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save
                        Slip</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Run payroll -->
    <div id="runModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Run payroll</h3>
                <button type="button" class="close-modal text-xl" data-target="runModal">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.payroll.run') }}" class="space-y-4">
                @csrf
                <p class="text-xs text-gray-600">Creates one slip for every active staff member who does not have one for
                    the month, using their saved salary structure. You can edit each slip afterwards.</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Month *</label>
                        <input type="month" name="month" required value="{{ $thisMonth }}"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Working days *</label>
                        <input type="number" min="1" max="31" name="working_days" required
                            value="{{ $defaultWorkingDays }}" class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="runModal">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg">Generate slips</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pay one -->
    <div id="payModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Mark as paid</h3>
                <button type="button" class="close-modal text-xl" data-target="payModal">&times;</button>
            </div>
            <form id="payForm" method="POST" action="" class="space-y-4">
                @csrf
                <p class="text-xs text-gray-600" id="payWho"></p>
                @include('admin.payroll._pay-fields', ['today' => $today])
                <div class="flex justify-end space-x-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="payModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Mark
                        paid</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pay all -->
    <div id="payAllModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Mark all paid</h3>
                <button type="button" class="close-modal text-xl" data-target="payAllModal">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.payroll.pay-all') }}" class="space-y-4"
                onsubmit="return confirm('Mark all {{ $stats['pending_count'] }} unpaid slips of this month as paid?');">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <p class="text-xs text-gray-600">
                    {{ $stats['pending_count'] }} unpaid slip(s) for
                    {{ $month !== 'all' ? \App\Models\SalarySlip::monthLabel($month) : '' }},
                    total {{ $m($stats['pending_amount']) }}. Use this after one bank transfer or one cash payout for
                    everyone.
                </p>
                @include('admin.payroll._pay-fields', ['today' => $today])
                <div class="flex justify-end space-x-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="payAllModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-amber-500 text-white text-xs font-bold rounded-lg">Mark all
                        paid</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View -->
    <div id="viewModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold" id="vTitle">Salary slip</h3>
                <button type="button" class="close-modal text-xl" data-target="viewModal">&times;</button>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs mb-4">
                <div>
                    <p class="font-bold uppercase text-gray-400">Employee</p>
                    <p id="v_staff" class="font-bold text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400">Month</p>
                    <p id="v_month"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400">Attendance</p>
                    <p id="v_att"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400">Status</p>
                    <p id="v_status" class="font-bold"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 text-xs">
                <div>
                    <p class="font-bold uppercase text-emerald-700 mb-1">Earnings</p>
                    <table class="w-full">
                        <tbody id="vEarn"></tbody>
                    </table>
                </div>
                <div>
                    <p class="font-bold uppercase text-rose-700 mb-1">Deductions</p>
                    <table class="w-full">
                        <tbody id="vDed"></tbody>
                    </table>
                </div>
            </div>
            <div class="mt-4 bg-emerald-600 text-white text-center font-bold rounded-lg py-2" id="vNet"></div>
            <p class="text-xs text-gray-600 mt-3" id="vNote"></p>
            <div class="flex justify-end pt-4 gap-2">
                <a id="vPrint" target="_blank" href="#"
                    class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg"><i class="bi bi-printer"></i> Print slip</a>
                <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                    data-target="viewModal">Close</button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var STORE_URL = @json(route('admin.payroll.store'));
            var BASE_URL = @json(url('/admin/payroll'));
            var STAFF = @json($staffJs);
            var CURRENT_MONTH = @json($thisMonth);
            var DEFAULT_DAYS = @json($defaultWorkingDays);

            var form = document.getElementById('slipForm');
            var earnBox = document.getElementById('earnBox');
            var dedBox = document.getElementById('dedBox');
            var idx = {
                earnings: 0,
                deductions: 0
            };
            var INPUT = 'w-full text-xs border rounded-lg p-2';

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

            function money(n) {
                n = num(n);
                return 'Rs. ' + n.toLocaleString('en-IN', {
                    maximumFractionDigits: 2
                });
            }

            function toList(x) {
                if (!x) return [];
                return Array.isArray(x) ? x : Object.keys(x).map(function(k) {
                    return x[k];
                });
            }

            function mk(tag, props) {
                var e = document.createElement(tag);
                Object.keys(props || {}).forEach(function(k) {
                    e[k] = props[k];
                });
                return e;
            }

            function addRow(kind, row) {
                row = row || {};
                var i = idx[kind]++;
                var wrap = mk('div', {
                    className: 'flex gap-2 mb-2 pay-line'
                });

                var name = mk('input', {
                    type: 'text',
                    name: kind + '[' + i + '][name]',
                    maxLength: 100,
                    className: INPUT,
                    value: row.name || '',
                    placeholder: kind === 'earnings' ? 'Allowance name' : 'Deduction name'
                });
                name.setAttribute('list', 'compList');

                var amt = mk('input', {
                    type: 'number',
                    step: '0.01',
                    min: '0',
                    name: kind + '[' + i + '][amount]',
                    placeholder: 'Amount',
                    className: INPUT + ' w-40 ' + (kind === 'earnings' ? 'e-amt' : 'd-amt'),
                    value: (row.amount !== undefined && row.amount !== null) ? row.amount : ''
                });

                var del = mk('button', {
                    type: 'button',
                    className: 'remove-row px-2 text-rose-600 font-bold',
                    textContent: '×',
                    title: 'Remove'
                });

                wrap.appendChild(name);
                wrap.appendChild(amt);
                wrap.appendChild(del);
                (kind === 'earnings' ? earnBox : dedBox).appendChild(wrap);
            }

            function clearRows() {
                earnBox.innerHTML = '';
                dedBox.innerHTML = '';
                idx.earnings = 0;
                idx.deductions = 0;
            }

            function recalc() {
                var basic = num(form.elements['basic'].value);
                var wd = num(form.elements['working_days'].value) || 1;
                var up = num(form.elements['unpaid_days'].value);
                var e = 0,
                    d = 0;
                document.querySelectorAll('.e-amt').forEach(function(i) {
                    e += num(i.value);
                });
                document.querySelectorAll('.d-amt').forEach(function(i) {
                    d += num(i.value);
                });

                var gross = round2(basic + e);
                var unpaid = up > 0 ? round2(gross / wd * up) : 0;
                var net = round2(gross - d - unpaid);

                document.getElementById('sGross').textContent = money(gross);
                document.getElementById('sUnpaid').textContent = '− ' + money(unpaid);
                document.getElementById('sDed').textContent = '− ' + money(d);
                var out = document.getElementById('sNet');
                out.textContent = money(net);
                out.className = 'font-bold ' + (net < 0 ? 'text-rose-700' : 'text-emerald-700');
            }

            function openForm(mode, data, id) {
                data = data || {};
                var edit = mode === 'edit';

                form.reset();
                clearRows();
                document.getElementById('formTitle').textContent = edit ? 'Edit Salary Slip' : 'Generate Salary Slip';
                form.setAttribute('action', edit ? BASE_URL + '/' + id : STORE_URL);
                document.getElementById('formMethod').disabled = !edit;
                document.getElementById('formMode').value = mode;
                document.getElementById('formEditId').value = id || '';

                var staffSel = form.elements['staff_member_id'],
                    monthIn = form.elements['month'];
                staffSel.value = data.staff_member_id || '';
                staffSel.disabled = edit;
                monthIn.value = data.month || CURRENT_MONTH;
                monthIn.disabled = edit;

                form.elements['basic'].value = (data.basic !== undefined && data.basic !== null) ? data.basic : '';
                form.elements['working_days'].value = data.working_days || DEFAULT_DAYS;
                form.elements['unpaid_days'].value = data.unpaid_days || '';
                form.elements['remarks'].value = data.remarks || '';

                toList(data.earnings).forEach(function(r) {
                    addRow('earnings', r);
                });
                toList(data.edit_deductions || data.deductions).forEach(function(r) {
                    addRow('deductions', r);
                });
                if (!earnBox.children.length) addRow('earnings');
                if (!dedBox.children.length) addRow('deductions');

                recalc();
                showModal('slipModal');
            }

            function rowData(btn) {
                try {
                    return JSON.parse(btn.closest('tr').dataset.slip);
                } catch (e) {
                    console.error(e);
                    return null;
                }
            }

            function openView(p) {
                function line(tbody, items) {
                    tbody.innerHTML = '';
                    items.forEach(function(l) {
                        var tr = mk('tr');
                        tr.appendChild(mk('td', {
                            className: 'py-1',
                            textContent: l.name
                        }));
                        tr.appendChild(mk('td', {
                            className: 'py-1 text-right',
                            textContent: money(l.amount)
                        }));
                        tbody.appendChild(tr);
                    });
                }
                document.getElementById('vTitle').textContent = 'Salary slip — ' + p.slip_no;
                document.getElementById('v_staff').textContent = p.staff_name + (p.designation ? ' · ' + p.designation :
                    '');
                document.getElementById('v_month').textContent = p.month_label;
                document.getElementById('v_att').textContent = (p.working_days - p.unpaid_days) + ' / ' + p
                    .working_days + ' days';
                document.getElementById('v_status').textContent = p.status.toUpperCase();

                var e = mk('tbody');
                var tb = document.getElementById('vEarn');
                tb.innerHTML = '';
                var basicRow = mk('tr');
                basicRow.appendChild(mk('td', {
                    className: 'py-1',
                    textContent: 'Basic salary'
                }));
                basicRow.appendChild(mk('td', {
                    className: 'py-1 text-right',
                    textContent: money(p.basic)
                }));
                tb.appendChild(basicRow);
                p.earnings.forEach(function(l) {
                    var tr = mk('tr');
                    tr.appendChild(mk('td', {
                        className: 'py-1',
                        textContent: l.name
                    }));
                    tr.appendChild(mk('td', {
                        className: 'py-1 text-right',
                        textContent: money(l.amount)
                    }));
                    tb.appendChild(tr);
                });
                line(document.getElementById('vDed'), p.deductions);

                document.getElementById('vNet').textContent = 'NET PAY: ' + money(p.net_pay);
                document.getElementById('vNote').textContent = p.status === 'paid' ?
                    'Paid on ' + p.paid_on + (p.mode_label ? ' via ' + p.mode_label : '') + (p.reference_no ?
                        ' · Ref ' + p.reference_no : '') :
                    (p.remarks || '');
                document.getElementById('vPrint').setAttribute('href', BASE_URL + '/' + p.id + '/print');
                showModal('viewModal');
            }

            // Pick a staff member: fill basic salary and standing allowances / deductions
            form.elements['staff_member_id'].addEventListener('change', function() {
                if (document.getElementById('formMode').value === 'edit') return;
                var s = STAFF[this.value];
                clearRows();
                if (s) {
                    form.elements['basic'].value = s.basic;
                    s.lines.forEach(function(l) {
                        addRow(l.type === 'earning' ? 'earnings' : 'deductions', l);
                    });
                }
                if (!earnBox.children.length) addRow('earnings');
                if (!dedBox.children.length) addRow('deductions');
                recalc();
            });

            form.addEventListener('input', recalc);

            document.addEventListener('click', function(e) {
                if (e.target.closest('#openCreateBtn')) {
                    openForm('create', {}, null);
                    return;
                }
                if (e.target.closest('#openRunBtn')) {
                    showModal('runModal');
                    return;
                }
                if (e.target.closest('#openPayAllBtn')) {
                    showModal('payAllModal');
                    return;
                }

                var closeBtn = e.target.closest('.close-modal');
                if (closeBtn) {
                    hideModal(closeBtn.getAttribute('data-target'));
                    return;
                }

                if (['slipModal', 'runModal', 'payModal', 'payAllModal', 'viewModal'].indexOf(e.target.id) !== -
                    1) {
                    hideModal(e.target.id);
                    return;
                }

                var add = e.target.closest('.add-row');
                if (add) {
                    addRow(add.getAttribute('data-kind'));
                    return;
                }

                var rm = e.target.closest('.remove-row');
                if (rm) {
                    rm.closest('.pay-line').remove();
                    recalc();
                    return;
                }

                var viewBtn = e.target.closest('.view-btn');
                if (viewBtn) {
                    var v = rowData(viewBtn);
                    if (v) openView(v);
                    return;
                }

                var editBtn = e.target.closest('.edit-btn');
                if (editBtn) {
                    var d = rowData(editBtn);
                    if (d) openForm('edit', d, d.id);
                    return;
                }

                var payBtn = e.target.closest('.pay-btn');
                if (payBtn) {
                    var p = rowData(payBtn);
                    if (!p) return;
                    document.getElementById('payForm').setAttribute('action', BASE_URL + '/' + p.id + '/pay');
                    document.getElementById('payWho').textContent = p.slip_no + ' · ' + p.staff_name + ' · ' +
                        money(p.net_pay);
                    showModal('payModal');
                }
            });

            // Reopen the form with the user's input if validation failed
            @if ($errors->any() && old('_mode'))
                var OLD = @json(old());
                openForm(OLD._mode === 'edit' ? 'edit' : 'create', OLD, OLD._edit_id || null);
            @endif
        })();
    </script>
@endsection
