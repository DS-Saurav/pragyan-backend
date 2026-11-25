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
        $badge = [
            'scheduled' => 'bg-sky-100 text-sky-800',
            'ongoing' => 'bg-amber-100 text-amber-800',
            'completed' => 'bg-violet-100 text-violet-800',
            'published' => 'bg-emerald-100 text-emerald-800',
        ];
        $tabs = [
            'all' => ['ALL', 'bg-slate-900 text-white'],
            'scheduled' => ['SCHEDULED', 'bg-sky-600 text-white'],
            'ongoing' => ['ONGOING', 'bg-amber-500 text-white'],
            'completed' => ['COMPLETED', 'bg-violet-600 text-white'],
            'published' => ['PUBLISHED', 'bg-emerald-600 text-white'],
        ];
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if (session('success'))
            <div
                class="no-print mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}</div>
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

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
            <h2 class="text-xl font-bold text-gray-900"><i class="bi bi-clipboard"></i> Exams Management</h2>
            <button type="button" id="openCreateBtn"
                class="no-print px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">+
                Create Exam</button>
        </div>

        <div class="no-print flex flex-wrap gap-2 mb-3">
            @foreach ($tabs as $key => [$label, $activeClass])
                <a href="{{ route('admin.exams.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition {{ $tab === $key ? $activeClass . ' shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <p class="no-print text-xs text-gray-500 mb-4">
            Flow: <b><i class="bi bi-gear"></i> Setup</b> subjects and marks → teachers enter <b><i class="bi bi-pencil-square"></i> Marks</b> (Save Draft → Submit → Lock) → admin
            checks <b><i class="bi bi-trophy"></i> Results</b> → admin <b><i class="bi bi-megaphone"></i> Publishes</b>.
        </p>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">
            <div class="no-print flex flex-col lg:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-printer"></i>
                        PRINT</button>
                </div>
                <form method="GET" action="{{ route('admin.exams.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <select name="per_page" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page
                            </option>
                        @endforeach
                    </select>
                    <select name="year" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All years</option>
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected(request('year') === $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search exam..."
                        class="text-xs border border-gray-300 rounded-lg p-2 w-44">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg">Search</button>
                </form>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Exam</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Year</th>
                            <th class="px-4 py-3">Start</th>
                            <th class="px-4 py-3">End</th>
                            <th class="px-4 py-3">Marks Progress</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($exams as $exam)
                            @php
                                $payload = [
                                    'id' => $exam->id,
                                    'name' => $exam->name,
                                    'type' => $exam->type,
                                    'academic_year' => $exam->academic_year,
                                    'status' => $exam->status,
                                    'start_date' => $exam->start_date->format('Y-m-d'),
                                    'end_date' => $exam->end_date?->format('Y-m-d'),
                                ];
                                $pct = $exam->exam_subjects_count
                                    ? round(($exam->submitted_count / $exam->exam_subjects_count) * 100)
                                    : 0;
                            @endphp
                            <tr class="hover:bg-gray-50/50" data-exam="{{ json_encode($payload) }}">
                                <td class="px-4 py-3 font-bold text-gray-900">{{ $exam->name }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-violet-100 text-violet-800 uppercase">{{ \App\Models\Exam::TYPES[$exam->type] ?? $exam->type }}</span>
                                </td>
                                <td class="px-4 py-3">{{ $exam->academic_year }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $exam->start_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $exam->end_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-3 min-w-[140px]">
                                    @if ($exam->exam_subjects_count)
                                        <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <div class="text-[11px] text-gray-500 mt-1">{{ $exam->submitted_count }} /
                                            {{ $exam->exam_subjects_count }} subjects submitted</div>
                                    @else
                                        <span class="text-[11px] text-amber-600 font-semibold">Not set up yet</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $badge[$exam->status] ?? 'bg-gray-100 text-gray-800' }}">{{ $exam->status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 no-print">
                                    <a href="{{ route('admin.exams.setup', $exam) }}" class="p-1 hover:text-indigo-600"
                                        title="Setup subjects & marks"><i class="bi bi-gear"></i></a>
                                    <a href="{{ route('admin.marks.index', ['exam_id' => $exam->id]) }}"
                                        class="p-1 hover:text-indigo-600" title="Enter marks"><i class="bi bi-pencil-square"></i></a>
                                    <a href="{{ route('admin.results.index', ['exam_id' => $exam->id]) }}"
                                        class="p-1 hover:text-amber-600" title="View results"><i class="bi bi-trophy"></i></a>

                                    @if ($exam->status === 'published')
                                        <form action="{{ route('admin.exams.unpublish', $exam) }}" method="POST"
                                            class="inline" onsubmit="return confirm('Unpublish these results?');">
                                            @csrf <button type="submit" class="p-1 hover:text-rose-600"
                                                title="Unpublish">↩</button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.exams.publish', $exam) }}" method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Publish results to students and parents?');">
                                            @csrf <button type="submit" class="p-1 hover:text-emerald-600"
                                                title="Publish results"><i class="bi bi-megaphone"></i></button>
                                        </form>
                                    @endif

                                    <button type="button" class="edit-exam-btn p-1 hover:text-indigo-600"
                                        title="Edit"><i class="bi bi-pencil"></i></button>
                                    @if ($exam->status !== 'published')
                                        <form action="{{ route('admin.exams.destroy', $exam) }}" method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Delete this exam? Its setup and ALL marks will be deleted too.');">
                                            @csrf @method('DELETE') <button type="submit" class="p-1 hover:text-rose-600"
                                                title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">No exams found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                <div>Showing {{ $exams->firstItem() ?? 0 }} to {{ $exams->lastItem() ?? 0 }} of {{ $exams->total() }}
                    entries</div>
                <div class="no-print">{{ $exams->links() }}</div>
            </div>
        </div>
    </div>

    <!-- Create / Edit Modal -->
    <div id="examFormModal" style="display:none;"
        class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold" id="examFormTitle">Create Exam</h3>
                <button type="button" class="close-modal text-xl" data-target="examFormModal">&times;</button>
            </div>
            <form id="examForm" method="POST" action="{{ route('admin.exams.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" id="examMethod" value="PUT" disabled>
                <input type="hidden" name="_mode" id="examMode" value="create">
                <input type="hidden" name="_edit_id" id="examEditId" value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Exam Name *</label>
                        <input type="text" name="name" required placeholder="e.g. First Terminal Exam"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Type</label>
                        <select name="type" required class="w-full text-xs border rounded-lg p-2.5">
                            @foreach (\App\Models\Exam::TYPES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Academic Year</label>
                        <input type="text" name="academic_year" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Status</label>
                        <select name="status" class="w-full text-xs border rounded-lg p-2.5">
                            <option value="scheduled">Scheduled</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Start Date *</label>
                        <input type="date" name="start_date" required class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">End Date</label>
                        <input type="date" name="end_date" class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="examFormModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg"
                        id="examSubmitBtn">Create Exam</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            var STORE_URL = @json(route('admin.exams.store'));
            var BASE_URL = @json(url('/admin/exams'));
            var DEFAULT_YEAR = @json($defaultYear);
            var FIELDS = ['name', 'type', 'academic_year', 'status', 'start_date', 'end_date'];

            function showModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'flex';
            }

            function hideModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            function openForm(mode, data, id) {
                var form = document.getElementById('examForm');
                data = data || {};
                var edit = mode === 'edit';

                document.getElementById('examFormTitle').textContent = edit ? 'Edit Exam' : 'Create Exam';
                document.getElementById('examSubmitBtn').textContent = edit ? 'Update Exam' : 'Create Exam';
                form.setAttribute('action', edit ? BASE_URL + '/' + id : STORE_URL);
                document.getElementById('examMethod').disabled = !edit;
                document.getElementById('examMode').value = mode;
                document.getElementById('examEditId').value = id || '';

                FIELDS.forEach(function(name) {
                    var el = form.elements[name];
                    if (el) el.value = (data[name] !== undefined && data[name] !== null) ? data[name] : '';
                });

                if (!form.elements['academic_year'].value) form.elements['academic_year'].value = DEFAULT_YEAR;
                if (!form.elements['status'].value) form.elements['status'].value = 'scheduled';
                if (!form.elements['type'].value) form.elements['type'].selectedIndex = 0;

                showModal('examFormModal');
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

                if (e.target.id === 'examFormModal') {
                    hideModal('examFormModal');
                    return;
                }

                var editBtn = e.target.closest('.edit-exam-btn');
                if (editBtn) {
                    try {
                        var d = JSON.parse(editBtn.closest('tr').dataset.exam);
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
