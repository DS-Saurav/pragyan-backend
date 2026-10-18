@extends('layouts.admin')

@section('content')
    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Success & Error Notifications -->
        @if (session('success'))
            <div
                class="mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg relative text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg relative text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Metric Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div
                class="bg-slate-900 text-white p-5 rounded-xl shadow-sm flex items-center justify-between border border-slate-800">
                <div>
                    <p class="text-2xl font-bold">{{ $counts['all'] ?? 0 }}</p>
                    <p class="text-xs uppercase tracking-wider text-slate-400 font-semibold mt-1">Total Teachers</p>
                </div>
                <div class="p-3 bg-slate-800 rounded-lg text-lg">👨‍🏫</div>
            </div>
            <div class="bg-emerald-600 text-white p-5 rounded-xl shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold">{{ $counts['active'] ?? 0 }}</p>
                    <p class="text-xs uppercase tracking-wider text-emerald-100 font-semibold mt-1">Active</p>
                </div>
                <div class="p-3 bg-emerald-700 rounded-lg text-lg">✅</div>
            </div>
            <div class="bg-sky-600 text-white p-5 rounded-xl shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold">{{ $counts['assignments'] ?? 0 }}</p>
                    <p class="text-xs uppercase tracking-wider text-sky-100 font-semibold mt-1">Total Assignments</p>
                </div>
                <div class="p-3 bg-sky-700 rounded-lg text-lg">📚</div>
            </div>
            <div class="bg-amber-600 text-white p-5 rounded-xl shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-2xl font-bold">{{ $counts['inactive'] ?? 0 }}</p>
                    <p class="text-xs uppercase tracking-wider text-amber-100 font-semibold mt-1">Inactive</p>
                </div>
                <div class="p-3 bg-amber-700 rounded-lg text-lg">❌</div>
            </div>
        </div>

        <!-- Status Tabs Bar -->
        <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100 mb-6 flex flex-wrap gap-2 items-center">
            <a href="{{ route('admin.teachers.index', ['tab' => 'all']) }}"
                class="px-4 py-2 rounded-lg text-xs font-bold transition {{ ($statusTab ?? 'all') === 'all' ? 'bg-slate-900 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                ALL ({{ $counts['all'] ?? 0 }})
            </a>
            <a href="{{ route('admin.teachers.index', ['tab' => 'active']) }}"
                class="px-4 py-2 rounded-lg text-xs font-bold transition {{ ($statusTab ?? '') === 'active' ? 'bg-emerald-600 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                ACTIVE ({{ $counts['active'] ?? 0 }})
            </a>
            <a href="{{ route('admin.teachers.index', ['tab' => 'inactive']) }}"
                class="px-4 py-2 rounded-lg text-xs font-bold transition {{ ($statusTab ?? '') === 'inactive' ? 'bg-gray-600 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                INACTIVE ({{ $counts['inactive'] ?? 0 }})
            </a>
            <a href="{{ route('admin.teachers.index', ['tab' => 'suspended']) }}"
                class="px-4 py-2 rounded-lg text-xs font-bold transition {{ ($statusTab ?? '') === 'suspended' ? 'bg-rose-600 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                SUSPENDED ({{ $counts['suspended'] ?? 0 }})
            </a>
        </div>

        <!-- Main Card -->
        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Header Row -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">👨‍🏫 Teachers Management</h3>
                    <p class="text-xs text-gray-500">Manage faculty members, specializations, class teacher duties, and
                        course assignments.</p>
                </div>
                <button type="button" id="openAddModalBtn"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                    + Register Teacher
                </button>
            </div>

            <!-- Toolbar: Export Buttons & Search -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 border-b pb-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.teachers.csv') }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow transition flex items-center gap-1">
                        📂 CSV
                    </a>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-md shadow transition flex items-center gap-1">
                        🖨️ PRINT
                    </button>
                </div>

                <form method="GET" action="{{ route('admin.teachers.index') }}" class="flex gap-2 w-full md:w-auto">
                    <input type="hidden" name="tab" value="{{ $statusTab ?? 'all' }}">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search teachers..."
                        class="text-xs border border-gray-300 rounded-lg p-2.5 w-full md:w-64 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">
                        Search
                    </button>
                </form>
            </div>

            <!-- Teachers Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Teacher</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Specialization</th>
                            <th class="px-4 py-3">Classes</th>
                            <th class="px-4 py-3">Subjects</th>
                            <th class="px-4 py-3">Class Teacher Of</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($teachers as $teacher)
                            @php
                                $assignmentsList = $teacher->assignments;
                                if (is_string($assignmentsList)) {
                                    $assignmentsList = json_decode($assignmentsList, true);
                                }
                                $assignmentsList = is_array($assignmentsList) ? array_values($assignmentsList) : [];

                                $assignmentsCount = count($assignmentsList);
                                $classesCount = count(
                                    array_unique(array_filter(array_column($assignmentsList, 'class'))),
                                );
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ $teacher->first_name }}
                                        {{ $teacher->last_name }}</div>
                                    <div class="text-[11px] text-gray-400 font-mono">
                                        {{ $teacher->username ?? '@' . strtolower($teacher->first_name) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-800">{{ $teacher->email }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $teacher->phone ?? 'N/A' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border uppercase">
                                        {{ $teacher->specialization ?? ($teacher->department ?? 'General') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-bold text-center text-indigo-600">{{ $classesCount }}</td>
                                <td class="px-4 py-3 font-bold text-center text-emerald-600">{{ $assignmentsCount }}</td>
                                <td class="px-4 py-3 font-medium">
                                    @if ($teacher->class_teacher_of)
                                        <span class="text-amber-600 font-bold">⭐ {{ $teacher->class_teacher_of }}</span>
                                    @else
                                        <span class="text-gray-400 italic">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($teacher->status === 'active')
                                        <span
                                            class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase">Active</span>
                                    @elseif ($teacher->status === 'inactive')
                                        <span
                                            class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-gray-100 text-gray-800 uppercase">Inactive</span>
                                    @else
                                        <span
                                            class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-rose-100 text-rose-800 uppercase">Suspended</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-x-1">
                                    <button type="button"
                                        class="manage-teacher-btn px-3 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded-md font-bold text-[11px] transition shadow"
                                        data-teacher="{{ json_encode([
                                            'id' => $teacher->id,
                                            'first_name' => $teacher->first_name,
                                            'last_name' => $teacher->last_name,
                                            'assignments' => $assignmentsList,
                                        ]) }}">
                                        Manage
                                    </button>
                                    <button type="button"
                                        class="edit-teacher-btn p-1 text-gray-400 hover:text-indigo-600 transition"
                                        title="Edit Details"
                                        data-teacher="{{ json_encode([
                                            'id' => $teacher->id,
                                            'first_name' => $teacher->first_name,
                                            'last_name' => $teacher->last_name,
                                            'email' => $teacher->email,
                                            'phone' => $teacher->phone,
                                            'department' => $teacher->department,
                                            'specialization' => $teacher->specialization,
                                            'class_teacher_of' => $teacher->class_teacher_of,
                                            'status' => $teacher->status,
                                        ]) }}">
                                        ✏️
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">No teachers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($teachers, 'links'))
                <div class="mt-4">{{ $teachers->links() }}</div>
            @endif
        </div>
    </div>

    <!-- Register Teacher Modal -->
    <div id="addTeacherModal" style="display:none;"
        class="fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Register New Teacher</h3>
                <button type="button" class="close-modal text-xl" data-target="addTeacherModal">&times;</button>
            </div>
            <form action="{{ route('admin.teachers.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">First Name</label>
                        <input type="text" name="first_name" required class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Last Name</label>
                        <input type="text" name="last_name" required class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Email Address</label>
                        <input type="email" name="email" required class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Phone Number</label>
                        <input type="text" name="phone" class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Department</label>
                        <input type="text" name="department" required placeholder="e.g. Science"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Specialization</label>
                        <input type="text" name="specialization" placeholder="e.g. Mathematics"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Class Teacher Of (Optional)</label>
                        <input type="text" name="class_teacher_of" placeholder="e.g. Class 1 A (2024-2025)"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Status</label>
                        <select name="status" required class="w-full text-xs border rounded-lg p-2.5">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="addTeacherModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save
                        Teacher</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Teacher Modal -->
    <div id="editTeacherModal" style="display:none;"
        class="fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Edit Teacher Details</h3>
                <button type="button" class="close-modal text-xl" data-target="editTeacherModal">&times;</button>
            </div>
            <form id="editTeacherForm" method="POST" action="" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">First Name</label>
                        <input type="text" name="first_name" id="edit_first_name" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Last Name</label>
                        <input type="text" name="last_name" id="edit_last_name" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Email Address</label>
                        <input type="email" name="email" id="edit_email" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Phone Number</label>
                        <input type="text" name="phone" id="edit_phone"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Department</label>
                        <input type="text" name="department" id="edit_department" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Specialization</label>
                        <input type="text" name="specialization" id="edit_specialization"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Class Teacher Of</label>
                        <input type="text" name="class_teacher_of" id="edit_class_teacher_of"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Status</label>
                        <select name="status" id="edit_status" required class="w-full text-xs border rounded-lg p-2.5">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="editTeacherModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg">Update
                        Teacher</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Assignments Modal -->
    <div id="manageTeacherModal" style="display:none;"
        class="fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold" id="manageModalTitle">Assignments — Teacher</h3>
                <button type="button" class="close-modal text-xl" data-target="manageTeacherModal">&times;</button>
            </div>

            <!-- Existing Assignments Table -->
            <div class="overflow-x-auto rounded-lg border border-gray-200 mb-6">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold">
                        <tr>
                            <th class="px-4 py-2.5">Class</th>
                            <th class="px-4 py-2.5">Subject</th>
                            <th class="px-4 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="assignmentsTableBody" class="divide-y divide-gray-200 bg-white"></tbody>
                </table>
            </div>

            <!-- Assign New Class & Subject Form -->
            <form id="assignForm" method="POST" action=""
                class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end bg-gray-50 p-4 rounded-xl border">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Class</label>
                    <select name="class_name" required class="w-full text-xs border rounded-lg p-2.5">
                        <option value="">Select class...</option>
                        @foreach ($classes ?? [] as $c)
                            <option value="{{ $c->name }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Subject</label>
                    <select name="subject_name" required class="w-full text-xs border rounded-lg p-2.5">
                        <option value="">Select subject...</option>
                        @foreach ($subjects ?? [] as $s)
                            <option value="{{ $s->name }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit"
                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                        + Assign Class & Subject
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            var CSRF = @json(csrf_token());

            function showModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'flex';
            }

            function hideModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            function esc(value) {
                var d = document.createElement('div');
                d.textContent = value == null ? '' : String(value);
                return d.innerHTML;
            }

            function openEditModal(t) {
                document.getElementById('editTeacherForm').setAttribute('action', '/admin/teachers/' + t.id);
                document.getElementById('edit_first_name').value = t.first_name || '';
                document.getElementById('edit_last_name').value = t.last_name || '';
                document.getElementById('edit_email').value = t.email || '';
                document.getElementById('edit_phone').value = t.phone || '';
                document.getElementById('edit_department').value = t.department || '';
                document.getElementById('edit_specialization').value = t.specialization || '';
                document.getElementById('edit_class_teacher_of').value = t.class_teacher_of || '';
                document.getElementById('edit_status').value = t.status || 'active';
                showModal('editTeacherModal');
            }

            function openManageModal(t) {
                document.getElementById('manageModalTitle').textContent =
                    'Assignments — ' + (t.first_name || '') + ' ' + (t.last_name || '');
                document.getElementById('assignForm').setAttribute('action', '/admin/teachers/' + t.id + '/assign');

                var tbody = document.getElementById('assignmentsTableBody');
                tbody.innerHTML = '';

                var list = Array.isArray(t.assignments) ? t.assignments : [];

                if (list.length > 0) {
                    list.forEach(function(a) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td class="px-4 py-3 font-bold text-gray-900">' + esc(a['class']) + '</td>' +
                            '<td class="px-4 py-3 text-gray-700">' + esc(a.subject) + '</td>' +
                            '<td class="px-4 py-3 text-right">' +
                            '<form action="/admin/teachers/' + t.id + '/assignment/' + a.id +
                            '" method="POST" class="inline" onsubmit="return confirm(\'Remove this assignment?\');">' +
                            '<input type="hidden" name="_token" value="' + esc(CSRF) + '">' +
                            '<input type="hidden" name="_method" value="DELETE">' +
                            '<button type="submit" class="text-rose-600 hover:text-rose-800 font-bold" title="Delete">🗑️</button>' +
                            '</form>' +
                            '</td>';
                        tbody.appendChild(tr);
                    });
                } else {
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td colspan="3" class="px-4 py-4 text-center text-gray-400 italic">No classes or subjects assigned yet.</td>';
                    tbody.appendChild(tr);
                }

                showModal('manageTeacherModal');
            }

            document.addEventListener('click', function(e) {
                if (e.target.closest('#openAddModalBtn')) {
                    showModal('addTeacherModal');
                    return;
                }

                var closeBtn = e.target.closest('.close-modal');
                if (closeBtn) {
                    hideModal(closeBtn.getAttribute('data-target'));
                    return;
                }

                if (e.target.id === 'addTeacherModal' || e.target.id === 'editTeacherModal' || e.target.id ===
                    'manageTeacherModal') {
                    hideModal(e.target.id);
                    return;
                }

                var editBtn = e.target.closest('.edit-teacher-btn');
                if (editBtn) {
                    try {
                        openEditModal(JSON.parse(editBtn.dataset.teacher));
                    } catch (err) {
                        console.error(err);
                    }
                    return;
                }

                var manageBtn = e.target.closest('.manage-teacher-btn');
                if (manageBtn) {
                    try {
                        openManageModal(JSON.parse(manageBtn.dataset.teacher));
                    } catch (err) {
                        console.error(err);
                    }
                }
            });
        })();
    </script>
@endsection
