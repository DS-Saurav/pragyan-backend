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
        $statusLabels = [
            'planned' => 'Planned',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'postponed' => 'Postponed',
        ];
        $badge = [
            'planned' => 'bg-blue-100 text-blue-800',
            'in_progress' => 'bg-amber-100 text-amber-800',
            'completed' => 'bg-emerald-100 text-emerald-800',
            'postponed' => 'bg-rose-100 text-rose-800',
        ];
        $tabs = [
            'all' => ['ALL', 'bg-slate-900 text-white'],
            'planned' => ['PLANNED', 'bg-sky-600 text-white'],
            'in_progress' => ['IN PROGRESS', 'bg-amber-500 text-white'],
            'completed' => ['COMPLETED', 'bg-emerald-600 text-white'],
            'postponed' => ['POSTPONED', 'bg-rose-600 text-white'],
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

        <!-- Page header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-5 gap-4">
            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">📝 Lesson Planning</h2>
            <button type="button" id="openCreateBtn"
                class="no-print inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                + New Lesson Plan
            </button>
        </div>

        <!-- Status tabs -->
        <div class="no-print flex flex-wrap gap-2 mb-5">
            @foreach ($tabs as $key => [$label, $activeClass])
                <a href="{{ route('admin.lesson-plans.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition {{ $tab === $key ? $activeClass . ' shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Toolbar -->
            <div class="no-print flex flex-col lg:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.lesson-plans.csv', request()->query()) }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow">📂
                        CSV</a>
                    <button type="button" onclick="window.print()" title="Choose 'Save as PDF' in the print dialog"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-md shadow">📄
                        PDF</button>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md shadow">🖨️
                        PRINT</button>
                </div>

                <form method="GET" action="{{ route('admin.lesson-plans.index') }}"
                    class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">

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

                    <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="From date">
                    <input type="date" name="to" value="{{ request('to') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="To date">

                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
                        class="text-xs border border-gray-300 rounded-lg p-2 w-40 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">Search</button>

                    @if (request()->hasAny(['search', 'class_id', 'teacher_id', 'from', 'to']))
                        <a href="{{ route('admin.lesson-plans.index', ['tab' => $tab]) }}"
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
                            <th class="px-4 py-3">Topic</th>
                            <th class="px-4 py-3">Homework</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($plans as $plan)
                            @php
                                $payload = [
                                    'id' => $plan->id,
                                    'lesson_date' => $plan->lesson_date->format('Y-m-d'),
                                    'period' => $plan->period,
                                    'duration_minutes' => $plan->duration_minutes,
                                    'teacher_id' => $plan->teacher_id,
                                    'school_class_id' => $plan->school_class_id,
                                    'subject_id' => $plan->subject_id,
                                    'unit' => $plan->unit,
                                    'topic' => $plan->topic,
                                    'objectives' => $plan->objectives,
                                    'teaching_method' => $plan->teaching_method,
                                    'resources' => $plan->resources,
                                    'homework' => $plan->homework,
                                    'assessment' => $plan->assessment,
                                    'reflection' => $plan->reflection,
                                    'status' => $plan->status,
                                    'status_label' => $statusLabels[$plan->status] ?? $plan->status,
                                    'teacher_name' => trim(
                                        ($plan->teacher->first_name ?? '') . ' ' . ($plan->teacher->last_name ?? ''),
                                    ),
                                    'class_name' => $plan->schoolClass->name ?? '',
                                    'subject_name' => $plan->subject->name ?? '',
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition" data-plan="{{ json_encode($payload) }}">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-gray-800">{{ $plan->lesson_date->format('Y-m-d') }}</div>
                                    @if ($plan->period || $plan->duration_minutes)
                                        <div class="text-[11px] text-gray-400">
                                            {{ $plan->period ? 'P' . $plan->period : '' }}
                                            {{ $plan->duration_minutes ? '· ' . $plan->duration_minutes . ' min' : '' }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $payload['teacher_name'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $payload['class_name'] ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $payload['subject_name'] ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ $plan->topic }}</div>
                                    @if ($plan->unit)
                                        <div class="text-[11px] text-gray-400">{{ $plan->unit }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $plan->homework ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $badge[$plan->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ $statusLabels[$plan->status] ?? $plan->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 no-print">
                                    <button type="button" class="view-plan-btn p-1 hover:text-indigo-600"
                                        title="View">👁️</button>

                                    @if ($plan->status !== 'completed')
                                        <form action="{{ route('admin.lesson-plans.status', $plan) }}" method="POST"
                                            class="inline" onsubmit="return confirm('Mark this lesson as completed?');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="p-1 hover:text-emerald-600"
                                                title="Mark completed">✅</button>
                                        </form>
                                    @endif

                                    <button type="button" class="edit-plan-btn p-1 hover:text-indigo-600"
                                        title="Edit">✏️</button>

                                    <form action="{{ route('admin.lesson-plans.destroy', $plan) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Delete this lesson plan?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 hover:text-rose-600"
                                            title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">No lesson plans found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                <div>
                    Showing {{ $plans->firstItem() ?? 0 }} to {{ $plans->lastItem() ?? 0 }} of {{ $plans->total() }}
                    entries
                </div>
                <div class="no-print">{{ $plans->links() }}</div>
            </div>
        </div>
    </div>

    <!-- Create / Edit Modal -->
    <div id="planFormModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold" id="planFormTitle">New Lesson Plan</h3>
                <button type="button" class="close-modal text-xl" data-target="planFormModal">&times;</button>
            </div>

            <form id="planForm" method="POST" action="{{ route('admin.lesson-plans.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" id="planMethod" value="PUT" disabled>
                <input type="hidden" name="_mode" id="planMode" value="create">
                <input type="hidden" name="_edit_id" id="planEditId" value="">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Date</label>
                        <input type="date" name="lesson_date" required class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Period (optional)</label>
                        <input type="number" name="period" min="1" max="12"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Duration (min)</label>
                        <input type="number" name="duration_minutes" min="5" max="240"
                            placeholder="e.g. 40" class="w-full text-xs border rounded-lg p-2.5">
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

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Unit / Chapter</label>
                        <input type="text" name="unit" placeholder="e.g. Unit 3: Addition"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Topic</label>
                        <input type="text" name="topic" required placeholder="e.g. Addition with carrying"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Learning Objectives</label>
                    <textarea name="objectives" rows="2" placeholder="By the end of the lesson, students will be able to..."
                        class="w-full text-xs border rounded-lg p-2.5"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Teaching Method</label>
                        <input type="text" name="teaching_method" placeholder="e.g. Demonstration, group work"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Resources / Materials</label>
                        <input type="text" name="resources" placeholder="e.g. Whiteboard, counters, charts"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Homework</label>
                        <input type="text" name="homework" placeholder="e.g. Workbook page 24"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Assessment</label>
                        <input type="text" name="assessment" placeholder="e.g. 5-question oral quiz"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Status</label>
                        <select name="status" required class="w-full text-xs border rounded-lg p-2.5">
                            @foreach ($statusLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold uppercase mb-1">Reflection / Notes after class</label>
                        <input type="text" name="reflection" placeholder="What worked? What to repeat next time?"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="planFormModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save
                        Lesson Plan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Modal -->
    <div id="planViewModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Lesson Plan Details</h3>
                <button type="button" class="close-modal text-xl" data-target="planViewModal">&times;</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Date</p>
                    <p id="v_lesson_date" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Period / Duration</p>
                    <p id="v_period" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Teacher</p>
                    <p id="v_teacher_name" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Class</p>
                    <p id="v_class_name" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Subject</p>
                    <p id="v_subject_name" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Status</p>
                    <p id="v_status_label" class="text-gray-900 font-bold"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Unit / Chapter</p>
                    <p id="v_unit" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Topic</p>
                    <p id="v_topic" class="text-gray-900 font-bold"></p>
                </div>
                <div class="md:col-span-2">
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Learning Objectives</p>
                    <p id="v_objectives" class="text-gray-900 whitespace-pre-line"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Teaching Method</p>
                    <p id="v_teaching_method" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Resources</p>
                    <p id="v_resources" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Homework</p>
                    <p id="v_homework" class="text-gray-900"></p>
                </div>
                <div>
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Assessment</p>
                    <p id="v_assessment" class="text-gray-900"></p>
                </div>
                <div class="md:col-span-2">
                    <p class="font-bold uppercase text-gray-400 mb-0.5">Reflection</p>
                    <p id="v_reflection" class="text-gray-900 whitespace-pre-line"></p>
                </div>
            </div>

            <div class="flex justify-end pt-4">
                <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                    data-target="planViewModal">Close</button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var STORE_URL = @json(route('admin.lesson-plans.store'));
            var BASE_URL = @json(url('/admin/lesson-plans'));
            var TODAY = @json(now()->toDateString());

            var FIELDS = ['lesson_date', 'period', 'duration_minutes', 'teacher_id', 'school_class_id', 'subject_id',
                'unit', 'topic', 'objectives', 'teaching_method', 'resources', 'homework', 'assessment',
                'status', 'reflection'
            ];

            var VIEW_FIELDS = ['lesson_date', 'teacher_name', 'class_name', 'subject_name', 'status_label', 'unit',
                'topic',
                'objectives', 'teaching_method', 'resources', 'homework', 'assessment', 'reflection'
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
                var form = document.getElementById('planForm');
                data = data || {};

                document.getElementById('planFormTitle').textContent = mode === 'edit' ? 'Edit Lesson Plan' :
                    'New Lesson Plan';
                form.setAttribute('action', mode === 'edit' ? BASE_URL + '/' + id : STORE_URL);
                document.getElementById('planMethod').disabled = (mode !== 'edit');
                document.getElementById('planMode').value = mode;
                document.getElementById('planEditId').value = id || '';

                FIELDS.forEach(function(name) {
                    var el = form.elements[name];
                    if (!el) return;
                    el.value = (data[name] !== undefined && data[name] !== null) ? data[name] : '';
                });

                if (!form.elements['lesson_date'].value) form.elements['lesson_date'].value = TODAY;
                if (!form.elements['status'].value) form.elements['status'].value = 'planned';

                showModal('planFormModal');
            }

            function openView(p) {
                VIEW_FIELDS.forEach(function(k) {
                    var el = document.getElementById('v_' + k);
                    if (el) el.textContent = (p[k] !== undefined && p[k] !== null && p[k] !== '') ? p[k] : '—';
                });

                var period = p.period ? 'Period ' + p.period : '';
                var duration = p.duration_minutes ? p.duration_minutes + ' min' : '';
                document.getElementById('v_period').textContent =
                    (period && duration) ? period + ' · ' + duration : (period || duration || '—');

                showModal('planViewModal');
            }

            function rowData(btn) {
                var tr = btn.closest('tr');
                try {
                    return JSON.parse(tr.dataset.plan);
                } catch (e) {
                    console.error(e);
                    return null;
                }
            }

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

                if (e.target.id === 'planFormModal' || e.target.id === 'planViewModal') {
                    hideModal(e.target.id);
                    return;
                }

                var editBtn = e.target.closest('.edit-plan-btn');
                if (editBtn) {
                    var d = rowData(editBtn);
                    if (d) openForm('edit', d, d.id);
                    return;
                }

                var viewBtn = e.target.closest('.view-plan-btn');
                if (viewBtn) {
                    var v = rowData(viewBtn);
                    if (v) openView(v);
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
