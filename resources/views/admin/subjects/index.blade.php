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

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Header Row -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i class="bi bi-book"></i> Subjects Management</h3>
                    <p class="text-xs text-gray-500">Manage curriculum subjects, course codes, max marks, and assigned
                        classes.</p>
                </div>
                <button type="button" id="openAddModalBtn"
                    class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                    + Add Subject
                </button>
            </div>

            <!-- Toolbar: Export Buttons & Search -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 border-b pb-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.subjects.csv') }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow transition flex items-center gap-1">
                        <i class="bi bi-folder2-open"></i> CSV
                    </a>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-md shadow transition flex items-center gap-1">
                        <i class="bi bi-printer"></i> PRINT
                    </button>
                </div>

                <form method="GET" action="{{ route('admin.subjects.index') }}" class="flex gap-2 w-full md:w-auto">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search subjects or codes..."
                        class="text-xs border border-gray-300 rounded-lg p-2.5 w-full md:w-64 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">
                        Search
                    </button>
                </form>
            </div>

            <!-- Subjects Data Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Max Marks</th>
                            <th class="px-4 py-3">Passing Marks</th>
                            <th class="px-4 py-3">Assigned Classes</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($subjects as $subject)
                            @php
                                $assigned = $subject->assigned_classes;
                                if (is_string($assigned)) {
                                    $assigned = json_decode($assigned, true);
                                }
                                $assigned = is_array($assigned) ? array_values($assigned) : [];
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-4 py-3 font-bold text-gray-900">{{ $subject->name }}</td>
                                <td class="px-4 py-3 text-gray-600 font-mono">{{ $subject->code }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-700">{{ $subject->max_marks }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-700">{{ $subject->passing_marks }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($assigned as $cls)
                                            <span
                                                class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                {{ $cls }}
                                            </span>
                                        @empty
                                            <span class="text-gray-400 italic">None</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right space-x-1">
                                    <button type="button"
                                        class="edit-subject-btn p-1 text-gray-400 hover:text-indigo-600 transition"
                                        title="Edit" data-url="{{ url('/admin/subjects/' . $subject->id) }}"
                                        data-name="{{ $subject->name }}" data-code="{{ $subject->code }}"
                                        data-max="{{ $subject->max_marks }}" data-pass="{{ $subject->passing_marks }}"
                                        data-classes="{{ json_encode($assigned) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form action="{{ route('admin.subjects.destroy', $subject->id) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Delete this subject?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 text-gray-400 hover:text-rose-600 transition"
                                            title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No subjects found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($subjects, 'links'))
                <div class="mt-4">{{ $subjects->links() }}</div>
            @endif
        </div>
    </div>

    <!-- Add Subject Modal -->
    <div id="addSubjectModal" style="display:none;"
        class="fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Add New Subject</h3>
                <button type="button" class="close-modal text-xl" data-target="addSubjectModal">&times;</button>
            </div>
            <form action="{{ route('admin.subjects.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Subject Name</label>
                    <input type="text" name="name" required placeholder="e.g. Computer Science"
                        class="w-full text-xs border rounded-lg p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Subject Code</label>
                    <input type="text" name="code" required placeholder="e.g. COMP-01"
                        class="w-full text-xs border rounded-lg p-2.5">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Max Marks</label>
                        <input type="number" name="max_marks" value="100" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Passing Marks</label>
                        <input type="number" name="passing_marks" value="33" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Assign Classes</label>
                    <div class="grid grid-cols-2 gap-2 max-h-32 overflow-y-auto border p-2 rounded-lg text-xs">
                        @forelse ($classes ?? [] as $c)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="assigned_classes[]" value="{{ $c->name }}"
                                    class="rounded border-gray-300 text-indigo-600">
                                {{ $c->name }}
                            </label>
                        @empty
                            <p class="text-gray-400 italic col-span-2">No classes created yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="addSubjectModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save
                        Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div id="editSubjectModal" style="display:none;"
        class="fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
            <div class="flex justify-between items-center pb-3 border-b mb-4">
                <h3 class="text-md font-bold">Edit Subject</h3>
                <button type="button" class="close-modal text-xl" data-target="editSubjectModal">&times;</button>
            </div>
            <form id="editSubjectForm" method="POST" action="" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Subject Name</label>
                    <input type="text" name="name" id="edit_name" required
                        class="w-full text-xs border rounded-lg p-2.5">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Subject Code</label>
                    <input type="text" name="code" id="edit_code" required
                        class="w-full text-xs border rounded-lg p-2.5">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Max Marks</label>
                        <input type="number" name="max_marks" id="edit_max_marks" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Passing Marks</label>
                        <input type="number" name="passing_marks" id="edit_passing_marks" required
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Assign Classes</label>
                    <div class="grid grid-cols-2 gap-2 max-h-32 overflow-y-auto border p-2 rounded-lg text-xs">
                        @forelse ($classes ?? [] as $c)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="assigned_classes[]" value="{{ $c->name }}"
                                    class="edit-class-checkbox rounded border-gray-300 text-indigo-600">
                                {{ $c->name }}
                            </label>
                        @empty
                            <p class="text-gray-400 italic col-span-2">No classes created yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="editSubjectModal">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg">Update
                        Subject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function() {
            function showModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'flex';
            }

            function hideModal(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }

            document.addEventListener('click', function(e) {
                // Open "Add" modal
                if (e.target.closest('#openAddModalBtn')) {
                    showModal('addSubjectModal');
                    return;
                }

                // Close buttons
                var closeBtn = e.target.closest('.close-modal');
                if (closeBtn) {
                    hideModal(closeBtn.getAttribute('data-target'));
                    return;
                }

                // Click on dark backdrop closes the modal
                if (e.target.id === 'addSubjectModal' || e.target.id === 'editSubjectModal') {
                    hideModal(e.target.id);
                    return;
                }

                // Edit button
                var btn = e.target.closest('.edit-subject-btn');
                if (btn) {
                    document.getElementById('editSubjectForm').setAttribute('action', btn.dataset.url);
                    document.getElementById('edit_name').value = btn.dataset.name || '';
                    document.getElementById('edit_code').value = btn.dataset.code || '';
                    document.getElementById('edit_max_marks').value = btn.dataset.max || '';
                    document.getElementById('edit_passing_marks').value = btn.dataset.pass || '';

                    var assigned = [];
                    try {
                        assigned = JSON.parse(btn.dataset.classes || '[]');
                    } catch (err) {
                        assigned = [];
                    }
                    if (!Array.isArray(assigned)) assigned = [];

                    document.querySelectorAll('.edit-class-checkbox').forEach(function(box) {
                        box.checked = assigned.indexOf(box.value) !== -1;
                    });

                    showModal('editSubjectModal');
                }
            });
        })();
    </script>
@endsection
