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
        $coverageLabels = [
            'completed' => 'Completed',
            'partial' => 'Partially covered',
            'not_covered' => 'Not covered',
        ];
        $badge = [
            'completed' => 'bg-emerald-100 text-emerald-800',
            'partial' => 'bg-amber-100 text-amber-800',
            'not_covered' => 'bg-rose-100 text-rose-800',
        ];
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if (session('success'))
            <div
                class="no-print mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}
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
            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">📖 Teaching Logbook</h2>
            <button type="button" id="openCreateBtn"
                class="no-print inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                + Add Log Entry
            </button>
        </div>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Toolbar -->
            <div class="no-print flex flex-col lg:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.teaching-logs.csv', request()->query()) }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow">📂
                        CSV</a>
                    <button type="button" onclick="window.print()" title="Choose 'Save as PDF' in the print dialog"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-md shadow">📄
                        PDF</button>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md shadow">🖨️
                        PRINT</button>
                </div>

                <form method="GET" action="{{ route('admin.teaching-logs.index') }}"
                    class="flex flex-wrap items-center gap-2">
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

                    <select name="teacher_id" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All teachers</option>
                        @foreach ($teachers as $t)
                            <option value="{{ $t->id }}" @selected((string) request('teacher_id') === (string) $t->id)>
                                {{ $t->first_name }} {{ $t->last_name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="coverage" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All coverage</option>
                        @foreach ($coverageLabels as $value => $label)
                            <option value="{{ $value }}" @selected(request('coverage') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="From date">
                    <input type="date" name="to" value="{{ request('to') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="To date">

                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
                        class="text-xs border border-gray-300 rounded-lg p-2 w-40 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">Search</button>

                    @if (request()->hasAny(['search', 'class_id', 'teacher_id', 'coverage', 'from', 'to']))
                        <a href="{{ route('admin.teaching-logs.index') }}"
                            class="px-3 py-2 bg-gray-200 text-xs font-bold rounded-lg">Reset</a>
                    @endif
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Teacher</th>
                            <th class="px-4 py-3">Class</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Topic Covered</th>
                            <th class="px-4 py-3">Coverage</th>
                            <th class="px-4 py-3">Attendance</th>
                            <th class="px-4 py-3">Homework Given</th>
                            <th class="px-4 py-3">Remarks</th>
                            <th class="px-4 py-3 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($logs as $log)
                            @php
                                $payload = [
                                    'id' => $log->id,
                                    'log_date' => $log->log_date->format('Y-m-d'),
                                    'period' => $log->period,
                                    'teacher_id' => $log->teacher_id,
                                    'school_class_id' => $log->school_class_id,
                                    'subject_id' => $log->subject_id,
                                    'lesson_plan_id' => $log->lesson_plan_id,
                                    'topic_covered' => $log->topic_covered,
                                    'coverage' => $log->coverage,
                                    'homework_given' => $log->homework_given,
                                    'students_present' => $log->students_present,
                                    'students_absent' => $log->students_absent,
                                    'remarks' => $log->remarks,
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition" data-log="{{ json_encode($payload) }}">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-gray-800">{{ $log->log_date->format('Y-m-d') }}</div>
                                    @if ($log->period)
                                        <div class="text-[11px] text-gray-400">Period {{ $log->period }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ trim(($log->teacher->first_name ?? '') . ' ' . ($log->teacher->last_name ?? '')) ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $log->schoolClass->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $log->subject->name ?? '—' }}</td>
                                <td class="px-4 py-3 font-bold text-gray-900">{{ $log->topic_covered }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase whitespace-nowrap {{ $badge[$log->coverage] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $coverageLabels[$log->coverage] ?? $log->coverage }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                    @if ($log->students_present !== null || $log->students_absent !== null)
                                        <span class="text-emerald-700 font-semibold">{{ $log->students_present ?? 0 }}
                                            P</span>
                                        /
                                        <span class="text-rose-700 font-semibold">{{ $log->students_absent ?? 0 }} A</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $log->homework_given ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 max-w-xs" title="{{ $log->remarks }}">
                                    {{ \Illuminate\Support\Str::limit($log->remarks, 60) ?: '—' }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 no-print">
                                    <button type="button" class="edit-log-btn p-1 hover:text-indigo-600"
                                        title="Edit">✏️</button>
                                    <form action="{{ route('admin.teaching-logs.destroy', $log) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Delete this log entry?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 hover:text-rose-600"
                                            title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-8 text-center text-gray-500">No log entries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                <div>Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }}
                    entries</div>
                <div class="no-print">{{ $logs->links() }}</div>
            </div>
        </div>
    </div>

    <!-- Create / Edit Modal -->
    <div id="logFormModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold" id="logFormTitle">Add Log Entry</h3>
                <button type="button" class="close-modal text-xl" data-target="logFormModal">&times;</button>
            </div>

            <form id="logForm" method="POST" action="{{ route('admin.teaching-logs.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" id="logMethod" value="PUT" disabled>
                <input type="hidden" name="_mode" id="logMode" value="create">
                <input type="hidden" name="_edit_id" id="logEditId" value="">

                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Link to Lesson Plan (optional)</label>
                    <select name="lesson_plan_id" id="lessonPlanSelect" class="w-full text-xs border rounded-lg p-2.5">
                        <option value="">— Not linked —</option>
                        @foreach ($lessonPlans as $lp)
                            <option value="{{ $lp->id }}"
                                data-plan="{{ json_encode([
                                    'teacher_id' => $lp->teacher_id,
                                    'school_class_id' => $lp->school_class_id,
                                    'subject_id' => $lp->subject_id,
                                    'topic_covered' => $lp->topic,
                                    'homework_given' => $lp->homework,
                                    'period' => $lp->period,
                                    'log_date' => $lp->lesson_date->format('Y-m-d'),
                                ]) }}">
                                {{ $lp->lesson_date->format('Y-m-d') }} · {{ $lp->schoolClass->name ?? '' }} ·
                                {{ $lp->subject->name ?? '' }} · {{ $lp->topic }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Picking a plan fills in the fields below. Saving with
                        "Completed" also marks that plan as completed.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Date</label>
                        <input type="date" name="log_date" required class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Period (optional)</label>
                        <input type="number" name="period" min="1" max="12"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Teacher</label>
                        <select name="teacher_id" required class="w-full text-xs border rounded-lg p-2.5">
                            <option value="">Select teacher...</option>
                            @foreach ($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->first_name }} {{ $t->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Class</label>
                        <select name="school_class_id" required class="w-full text-xs border rounded-lg p-2.5">
                            <option value="">Select class...</option>
                            @foreach ($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Subject</label>
                        <select name="subject_id" required class="w-full text-xs border rounded-lg p-2.5">
                            <option value="">Select subject...</option>
                            @foreach ($subjects as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold uppercase mb-1">Topic Covered</label>
                        <input type="text" name="topic_covered" required placeholder="e.g. Addition basics"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Coverage</label>
                        <select name="coverage" required class="w-full text-xs border rounded-lg p-2.5">
                            @foreach ($coverageLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold uppercase mb-1">Students Present</label>
                        <input type="number" name="students_present" min="0" max="500"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold uppercase mb-1">Students Absent</label>
                        <input type="number" name="students_absent" min="0" max="500"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold uppercase mb-1">Homework Given</label>
                        <input type="text" name="homework_given" placeholder="e.g. Workbook page 23"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Remarks</label>
                    <textarea name="remarks" rows="2" placeholder="e.g. 2 students need revision support"
                        class="w-full text-xs border rounded-lg p-2.5"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="logFormModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save
                        Log Entry</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            var STORE_URL = @json(route('admin.teaching-logs.store'));
            var BASE_URL = @json(url('/admin/teaching-logs'));
            var TODAY = @json(now()->toDateString());

            var FIELDS = ['log_date', 'period', 'teacher_id', 'school_class_id', 'subject_id', 'lesson_plan_id',
                'topic_covered', 'coverage', 'homework_given', 'students_present', 'students_absent', 'remarks'
            ];

            function showModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'flex';
            }

            function hideModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            function openForm(mode, data, id) {
                var form = document.getElementById('logForm');
                data = data || {};

                document.getElementById('logFormTitle').textContent = mode === 'edit' ? 'Edit Log Entry' :
                    'Add Log Entry';
                form.setAttribute('action', mode === 'edit' ? BASE_URL + '/' + id : STORE_URL);
                document.getElementById('logMethod').disabled = (mode !== 'edit');
                document.getElementById('logMode').value = mode;
                document.getElementById('logEditId').value = id || '';

                FIELDS.forEach(function(name) {
                    var el = form.elements[name];
                    if (!el) return;
                    el.value = (data[name] !== undefined && data[name] !== null) ? data[name] : '';
                });

                if (!form.elements['log_date'].value) form.elements['log_date'].value = TODAY;
                if (!form.elements['coverage'].value) form.elements['coverage'].value = 'completed';

                showModal('logFormModal');
            }

            // Pre-fill from a lesson plan
            document.getElementById('lessonPlanSelect').addEventListener('change', function() {
                var opt = this.options[this.selectedIndex];
                if (!opt || !opt.dataset.plan) return;

                var plan;
                try {
                    plan = JSON.parse(opt.dataset.plan);
                } catch (e) {
                    return;
                }

                var form = document.getElementById('logForm');
                ['teacher_id', 'school_class_id', 'subject_id', 'topic_covered', 'homework_given', 'period',
                    'log_date'
                ]
                .forEach(function(name) {
                    if (plan[name] !== undefined && plan[name] !== null && form.elements[name]) {
                        form.elements[name].value = plan[name];
                    }
                });
            });

            document.addEventListener('click', function(e) {
                if (e.target.closest('#openCreateBtn')) {
                    openForm('create', {}, null);
                    return;
                }

                var closeBtn = e.target.closest('.close-modal');
                if (closeBtn) {
                    hideModal(closeBtn.getAttribute('data-target'));
                    return;
                }

                if (e.target.id === 'logFormModal') {
                    hideModal('logFormModal');
                    return;
                }

                var editBtn = e.target.closest('.edit-log-btn');
                if (editBtn) {
                    try {
                        var d = JSON.parse(editBtn.closest('tr').dataset.log);
                        openForm('edit', d, d.id);
                    } catch (err) {
                        console.error(err);
                    }
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
