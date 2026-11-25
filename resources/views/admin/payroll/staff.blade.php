@extends('layouts.admin')

@section('content')
    @php
        $m = fn($v) => \App\Support\Money::format($v);
        $statusBadge = ['active' => 'bg-emerald-100 text-emerald-800', 'inactive' => 'bg-gray-200 text-gray-700'];
        $canManage = auth()->user()->can('payroll.manage');
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
                <a href="{{ route('admin.payroll.index') }}" class="text-xs text-indigo-600 font-semibold">← Back to
                    Payroll</a>
                <h2 class="text-xl font-bold text-gray-900"><i class="bi bi-people"></i> Staff &amp; Salary Setup</h2>
                <p class="text-xs text-gray-500">Each person's basic salary and standing allowances / deductions. "Run
                    payroll" uses this to create slips.</p>
            </div>
            @if ($canManage)
                <div class="flex flex-wrap gap-2">
                    @if ($notImported > 0)
                        <form method="POST" action="{{ route('admin.payroll.staff.import') }}"
                            onsubmit="return confirm('Copy {{ $notImported }} teacher(s) into payroll?');">
                            @csrf
                            <button type="submit"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow"><i class="bi bi-download"></i>
                                Import {{ $notImported }} teacher(s)</button>
                        </form>
                    @endif
                    <button type="button" id="openCreateBtn"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">+
                        Add staff</button>
                </div>
            @endif
        </div>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100 mb-6">
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Designation</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Basic</th>
                            <th class="px-4 py-3">Standing pay lines</th>
                            <th class="px-4 py-3">Bank / PAN</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($staff as $s)
                            @php
                                $payload = [
                                    'id' => $s->id,
                                    'name' => $s->name,
                                    'designation' => $s->designation,
                                    'type' => $s->type,
                                    'phone' => $s->phone,
                                    'joined_on' => $s->joined_on?->format('Y-m-d'),
                                    'basic_salary' => $s->basic_salary,
                                    'bank_name' => $s->bank_name,
                                    'bank_account' => $s->bank_account,
                                    'pan_no' => $s->pan_no,
                                    'status' => $s->status,
                                    'components' => $s->components
                                        ->map(fn($c) => ['id' => $c->id, 'value' => (float) $c->pivot->value])
                                        ->values(),
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50 {{ $s->status === 'inactive' ? 'opacity-60' : '' }}"
                                data-staff="{{ json_encode($payload) }}">
                                <td class="px-4 py-3 font-bold text-gray-900">{{ $s->name }}</td>
                                <td class="px-4 py-3">{{ $s->designation ?: '—' }}</td>
                                <td class="px-4 py-3">{{ \App\Models\StaffMember::TYPES[$s->type] ?? $s->type }}</td>
                                <td
                                    class="px-4 py-3 whitespace-nowrap {{ $s->basic_salary <= 0 ? 'text-rose-600 font-bold' : '' }}">
                                    {{ $s->basic_salary > 0 ? $m($s->basic_salary) : 'Not set' }}</td>
                                <td class="px-4 py-3">
                                    @forelse ($s->defaultLines() as $l)
                                        <span
                                            class="inline-block mr-1 mb-1 px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $l['type'] === 'earning' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100' }}">
                                            {{ $l['type'] === 'earning' ? '+' : '−' }} {{ $l['name'] }}
                                            {{ $m($l['amount']) }}
                                        </span>
                                    @empty
                                        <span class="text-gray-400 italic">None</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-3 text-[11px] text-gray-500">
                                    {{ $s->bank_name ?: '—' }}{{ $s->bank_account ? ' · ' . $s->bank_account : '' }}
                                    @if ($s->pan_no)
                                        <br>PAN {{ $s->pan_no }}
                                    @endif
                                </td>
                                <td class="px-4 py-3"><span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $statusBadge[$s->status] ?? '' }}">{{ $s->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($canManage)
                                        <button type="button" class="edit-btn p-1 hover:text-indigo-600"
                                            title="Edit"><i class="bi bi-pencil"></i></button>
                                        <form action="{{ route('admin.payroll.staff.destroy', $s) }}" method="POST"
                                            class="inline" onsubmit="return confirm('Delete this staff member?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-1 hover:text-rose-600"
                                                title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">No staff yet. Import your
                                    teachers or add staff by hand.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Components -->
        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">
            <h3 class="text-sm font-bold text-gray-900 mb-1">Allowance and deduction types</h3>
            <p class="text-[11px] text-gray-400 mb-3">The pay lines you can give to staff. A percentage is taken from the
                basic salary. Provident fund and tax rates here are examples: confirm them with your accountant. Types
                already given to staff cannot be deleted, only switched off.</p>

            @foreach ($components as $c)
                <form method="POST" action="{{ route('admin.payroll.components.update', $c) }}"
                    class="flex flex-wrap items-center gap-2 py-2 border-b last:border-0">
                    @csrf @method('PUT')
                    <input type="text" name="name" value="{{ $c->name }}" required maxlength="80"
                        @disabled(!$canManage) class="text-xs border rounded-lg p-2 w-56">
                    <select name="type" @disabled(!$canManage) class="text-xs border rounded-lg p-2">
                        @foreach (\App\Models\SalaryComponent::TYPES as $v => $l)
                            <option value="{{ $v }}" @selected($c->type === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                    <select name="calc" @disabled(!$canManage) class="text-xs border rounded-lg p-2">
                        @foreach (\App\Models\SalaryComponent::CALCS as $v => $l)
                            <option value="{{ $v }}" @selected($c->calc === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0" name="default_value"
                        value="{{ \App\Services\ResultService::n($c->default_value) }}" @disabled(!$canManage)
                        class="text-xs border rounded-lg p-2 w-24" title="Default value">
                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1"
                            @checked($c->is_active) @disabled(!$canManage) class="rounded border-gray-300">
                        Active</label>
                    @if ($canManage)
                        <button type="submit"
                            class="px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md">Save</button>
                        @unless ($usedComponentIds->contains($c->id))
                            <button type="submit" form="delcomp-{{ $c->id }}" class="px-2 text-rose-600" title="Delete"
                                onclick="return confirm('Delete this type?');"><i class="bi bi-trash"></i></button>
                        @endunless
                    @endif
                </form>
            @endforeach

            @if ($canManage)
                @foreach ($components as $c)
                    @unless ($usedComponentIds->contains($c->id))
                        <form id="delcomp-{{ $c->id }}" method="POST"
                            action="{{ route('admin.payroll.components.destroy', $c) }}" class="hidden">@csrf @method('DELETE')
                        </form>
                    @endunless
                @endforeach

                <form method="POST" action="{{ route('admin.payroll.components.store') }}"
                    class="flex flex-wrap items-center gap-2 pt-4">
                    @csrf
                    <input type="text" name="name" required maxlength="80"
                        placeholder="New type, e.g. Medical Allowance" class="text-xs border rounded-lg p-2 w-56">
                    <select name="type" class="text-xs border rounded-lg p-2">
                        @foreach (\App\Models\SalaryComponent::TYPES as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </select>
                    <select name="calc" class="text-xs border rounded-lg p-2">
                        @foreach (\App\Models\SalaryComponent::CALCS as $v => $l)
                            <option value="{{ $v }}">{{ $l }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0" name="default_value" placeholder="Default"
                        class="text-xs border rounded-lg p-2 w-24">
                    <button type="submit"
                        class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-md">+
                        Add type</button>
                </form>
            @endif
        </div>
    </div>

    <!-- Staff modal -->
    <div id="staffModal" style="display:none;"
        class="fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 max-h-[92vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold" id="formTitle">Add staff</h3>
                <button type="button" class="close-modal text-xl" data-target="staffModal">&times;</button>
            </div>

            <form id="staffForm" method="POST" action="{{ route('admin.payroll.staff.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="PUT" disabled>
                <input type="hidden" name="_mode" id="formMode" value="create">
                <input type="hidden" name="_edit_id" id="formEditId" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><label class="block text-xs font-bold uppercase mb-1">Full name *</label>
                        <input type="text" name="name" required maxlength="120"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div><label class="block text-xs font-bold uppercase mb-1">Designation</label>
                        <input type="text" name="designation" maxlength="100"
                            placeholder="e.g. Math Teacher, Accountant, Peon"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div><label class="block text-xs font-bold uppercase mb-1">Type *</label>
                        <select name="type" required class="w-full text-xs border rounded-lg p-2.5">
                            @foreach (\App\Models\StaffMember::TYPES as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label class="block text-xs font-bold uppercase mb-1">Status *</label>
                        <select name="status" required class="w-full text-xs border rounded-lg p-2.5">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div><label class="block text-xs font-bold uppercase mb-1">Basic salary (Rs.) *</label>
                        <input type="number" step="0.01" min="0" name="basic_salary" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div><label class="block text-xs font-bold uppercase mb-1">Joined on</label>
                        <input type="date" name="joined_on" class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div><label class="block text-xs font-bold uppercase mb-1">Phone</label>
                        <input type="text" name="phone" maxlength="30"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div><label class="block text-xs font-bold uppercase mb-1">PAN no.</label>
                        <input type="text" name="pan_no" maxlength="30"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div><label class="block text-xs font-bold uppercase mb-1">Bank name</label>
                        <input type="text" name="bank_name" maxlength="80"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div><label class="block text-xs font-bold uppercase mb-1">Bank account no.</label>
                        <input type="text" name="bank_account" maxlength="40"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1">
                        <p class="text-xs font-bold uppercase text-indigo-600">Standing allowances and deductions</p>
                        <button type="button" id="addCompBtn"
                            class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-[11px] font-bold rounded-md">+ Add
                            line</button>
                    </div>
                    <div id="compBox"></div>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="staffModal">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            var STORE_URL = @json(route('admin.payroll.staff.store'));
            var BASE_URL = @json(url('/admin/payroll/staff'));
            var COMPONENTS = @json($components->where('is_active', true)->values());
            var FIELDS = ['name', 'designation', 'type', 'status', 'basic_salary', 'joined_on', 'phone', 'pan_no',
                'bank_name', 'bank_account'
            ];

            var form = document.getElementById('staffForm');
            var box = document.getElementById('compBox');
            var idx = 0;

            function showModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'flex';
            }

            function hideModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            function mk(tag, props) {
                var e = document.createElement(tag);
                Object.keys(props || {}).forEach(function(k) {
                    e[k] = props[k];
                });
                return e;
            }

            function toList(x) {
                if (!x) return [];
                return Array.isArray(x) ? x : Object.keys(x).map(function(k) {
                    return x[k];
                });
            }

            function comp(id) {
                return COMPONENTS.filter(function(c) {
                    return String(c.id) === String(id);
                })[0];
            }

            function addLine(row) {
                row = row || {};
                var i = idx++;
                var wrap = mk('div', {
                    className: 'flex gap-2 mb-2 items-center comp-line'
                });

                var sel = mk('select', {
                    name: 'components[' + i + '][id]',
                    className: 'comp-sel w-full text-xs border rounded-lg p-2'
                });
                sel.appendChild(mk('option', {
                    value: '',
                    textContent: 'Select type...'
                }));
                COMPONENTS.forEach(function(c) {
                    sel.appendChild(mk('option', {
                        value: c.id,
                        textContent: c.name + (c.type === 'deduction' ? '  (deduction)' :
                            '  (allowance)')
                    }));
                });
                sel.value = row.id || '';

                var val = mk('input', {
                    type: 'number',
                    step: '0.01',
                    min: '0',
                    name: 'components[' + i + '][value]',
                    placeholder: 'Value',
                    className: 'comp-val w-32 text-xs border rounded-lg p-2',
                    value: row.value !== undefined ? row.value : ''
                });
                var unit = mk('span', {
                    className: 'comp-unit text-[11px] text-gray-500 w-16'
                });
                var del = mk('button', {
                    type: 'button',
                    className: 'remove-line px-2 text-rose-600 font-bold',
                    textContent: '×'
                });

                wrap.appendChild(sel);
                wrap.appendChild(val);
                wrap.appendChild(unit);
                wrap.appendChild(del);
                box.appendChild(wrap);
                setUnit(wrap, false);
            }

            function setUnit(wrap, fillDefault) {
                var c = comp(wrap.querySelector('.comp-sel').value);
                wrap.querySelector('.comp-unit').textContent = c ? (c.calc === 'percent' ? '% of basic' : 'Rs.') : '';
                var v = wrap.querySelector('.comp-val');
                if (fillDefault && c && !v.value) v.value = c.default_value || '';
            }

            function openForm(mode, data, id) {
                data = data || {};
                var edit = mode === 'edit';

                form.reset();
                box.innerHTML = '';
                idx = 0;
                document.getElementById('formTitle').textContent = edit ? 'Edit staff' : 'Add staff';
                form.setAttribute('action', edit ? BASE_URL + '/' + id : STORE_URL);
                document.getElementById('formMethod').disabled = !edit;
                document.getElementById('formMode').value = mode;
                document.getElementById('formEditId').value = id || '';

                FIELDS.forEach(function(n) {
                    var el = form.elements[n];
                    if (el) el.value = (data[n] !== undefined && data[n] !== null) ? data[n] : '';
                });
                if (!form.elements['type'].value) form.elements['type'].value = 'teaching';
                if (!form.elements['status'].value) form.elements['status'].value = 'active';

                toList(data.components).forEach(addLine);
                showModal('staffModal');
            }

            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('comp-sel')) setUnit(e.target.closest('.comp-line'), true);
            });

            document.addEventListener('click', function(e) {
                if (e.target.closest('#openCreateBtn')) {
                    openForm('create', {}, null);
                    return;
                }
                if (e.target.closest('#addCompBtn')) {
                    addLine();
                    return;
                }

                var closeBtn = e.target.closest('.close-modal');
                if (closeBtn) {
                    hideModal(closeBtn.getAttribute('data-target'));
                    return;
                }
                if (e.target.id === 'staffModal') {
                    hideModal('staffModal');
                    return;
                }

                var rm = e.target.closest('.remove-line');
                if (rm) {
                    rm.closest('.comp-line').remove();
                    return;
                }

                var editBtn = e.target.closest('.edit-btn');
                if (editBtn) {
                    try {
                        var d = JSON.parse(editBtn.closest('tr').dataset.staff);
                        openForm('edit', d, d.id);
                    } catch (err) {
                        console.error(err);
                    }
                }
            });

            @if ($errors->any() && old('_mode'))
                var OLD = @json(old());
                openForm(OLD._mode === 'edit' ? 'edit' : 'create', OLD, OLD._edit_id || null);
            @endif
        })();
    </script>
@endsection
