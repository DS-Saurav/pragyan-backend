@extends('layouts.admin')

@section('content')
    <style>
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff !important; }
        }
    </style>

    <div x-data="{ openAddModal: false, openEditModal: false, openChildrenModal: false, currentParent: {} }" class="space-y-6">

        <!-- Header -->
        <div class="flex justify-between items-center print:hidden">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Parents Management</h2>
                <p class="text-xs text-gray-500 mt-1">Parents are managed locally for Birendranagar students. You can link
                    multiple children to one parent.</p>
            </div>
            <button @click="openAddModal = true"
                class="px-4 py-2 bg-emerald-600 text-white font-semibold text-sm rounded-lg hover:bg-emerald-700 transition shadow-sm">
                + Add Parent
            </button>
        </div>

        <!-- Print-only heading -->
        <div class="hidden print:block">
            <h2 class="text-lg font-bold text-gray-900">Parents List</h2>
            <p class="text-xs text-gray-500">Printed on {{ now()->format('d M Y, h:i A') }}</p>
        </div>

        @if (session('success'))
            <div class="p-4 bg-emerald-100 text-emerald-700 rounded-lg text-sm font-medium print:hidden">
                {{ session('success') }}
            </div>
        @endif

        <!-- Data Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden print:shadow-none print:border-0">

            <!-- Search + Export Toolbar -->
            <div class="p-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3 print:hidden">
                <form method="GET" action="{{ route('admin.parents.index') }}" class="flex items-center space-x-2">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search parents..."
                        class="border-gray-300 rounded-lg text-sm px-3 py-1.5 w-64">
                    <button type="submit"
                        class="px-3 py-1.5 bg-slate-900 text-white text-xs rounded-lg font-bold">Search</button>
                </form>

                <div class="flex items-center space-x-2">
                    <!-- CSV (all matching rows, respects current search) -->
                    <a href="{{ route('admin.parents.export.csv', request()->only('search')) }}"
                        class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded shadow transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        CSV
                    </a>

                    <!-- PDF (real file download, all matching rows) -->
                    <a href="{{ route('admin.parents.export.pdf', request()->only('search')) }}"
                        class="inline-flex items-center px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded shadow transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                        PDF
                    </a>

                    <!-- Print (current page only) -->
                    <button type="button" onclick="window.print()"
                        class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded shadow transition">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                            </path>
                        </svg>
                        PRINT
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="export-table" class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-xs tracking-wider print:bg-gray-200 print:text-black">
                            <th class="p-4">Parent</th>
                            <th class="p-4">Relation</th>
                            <th class="p-4">Mobile</th>
                            <th class="p-4">Email</th>
                            <th class="p-4">Occupation</th>
                            <th class="p-4">Children</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right print:hidden">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($parents as $parent)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-4 font-semibold text-gray-900">{{ $parent->name }}</td>
                                <td class="p-4"><span
                                        class="px-2 py-0.5 bg-sky-50 text-sky-700 rounded text-xs font-bold">{{ strtoupper($parent->relation) }}</span>
                                </td>
                                <td class="p-4 text-gray-600">{{ $parent->mobile }}</td>
                                <td class="p-4 text-gray-600">{{ $parent->email ?? 'N/A' }}</td>
                                <td class="p-4 text-gray-600">{{ $parent->occupation ?? 'N/A' }}</td>
                                <td class="p-4">
                                    <button @click="currentParent = {{ json_encode($parent) }}; openChildrenModal = true"
                                        class="text-xs font-bold text-indigo-600 hover:underline">
                                        {{ $parent->students->count() }} linked
                                    </button>
                                </td>
                                <td class="p-4">
                                    <span
                                        class="px-2.5 py-1 {{ $parent->status == 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }} rounded-full text-xs font-bold">
                                        {{ strtoupper($parent->status) }}
                                    </span>
                                </td>
                                <td class="p-4 text-right space-x-2 print:hidden">
                                    <button @click="currentParent = {{ json_encode($parent) }}; openChildrenModal = true"
                                        class="p-1.5 bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100 text-xs"
                                        title="Manage Children">🎓</button>
                                    <button @click="currentParent = {{ json_encode($parent) }}; openEditModal = true"
                                        class="p-1.5 bg-amber-50 text-amber-600 rounded hover:bg-amber-100 text-xs"
                                        title="Edit">✏️</button>
                                    <form action="{{ route('admin.parents.destroy', $parent->id) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Delete this parent?')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                            class="p-1.5 bg-rose-50 text-rose-600 rounded hover:bg-rose-100 text-xs"
                                            title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-400">No parents found. Parents are added
                                    automatically during student registration or manually here.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-200 print:hidden">
                {{ $parents->links() }}
            </div>
        </div>

        <!-- Add Parent Modal -->
        <div x-show="openAddModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm print:hidden"
            style="display: none;">
            <div @click.outside="openAddModal = false"
                class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-200">
                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="font-bold text-base">Add Parent</h3>
                    <button @click="openAddModal = false"
                        class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>
                <form action="{{ route('admin.parents.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Name *</label>
                            <input type="text" name="name" required class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Relation *</label>
                            <select name="relation" class="w-full border-gray-300 rounded-lg text-sm">
                                <option value="Father">Father</option>
                                <option value="Mother">Mother</option>
                                <option value="Guardian">Guardian</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Mobile *</label>
                            <input type="text" name="mobile" required class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email</label>
                            <input type="email" name="email" class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Occupation</label>
                            <input type="text" name="occupation" class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status *</label>
                            <select name="status" class="w-full border-gray-300 rounded-lg text-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Address</label>
                            <input type="text" name="address" class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="openAddModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium">Add Parent</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Parent Modal -->
        <div x-show="openEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm print:hidden"
            style="display: none;">
            <div @click.outside="openEditModal = false"
                class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-200">
                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="font-bold text-base">Edit Parent</h3>
                    <button @click="openEditModal = false"
                        class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>
                <form :action="'/admin/parents/' + currentParent.id" method="POST" class="p-6 space-y-4">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Name *</label>
                            <input type="text" name="name" x-model="currentParent.name" required
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Relation *</label>
                            <select name="relation" x-model="currentParent.relation"
                                class="w-full border-gray-300 rounded-lg text-sm">
                                <option value="Father">Father</option>
                                <option value="Mother">Mother</option>
                                <option value="Guardian">Guardian</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Mobile *</label>
                            <input type="text" name="mobile" x-model="currentParent.mobile" required
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Email</label>
                            <input type="email" name="email" x-model="currentParent.email"
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Occupation</label>
                            <input type="text" name="occupation" x-model="currentParent.occupation"
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status *</label>
                            <select name="status" x-model="currentParent.status"
                                class="w-full border-gray-300 rounded-lg text-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Address</label>
                            <input type="text" name="address" x-model="currentParent.address"
                                class="w-full border-gray-300 rounded-lg text-sm">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="openEditModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium">Update
                            Parent</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Manage Linked Children Modal -->
        <div x-show="openChildrenModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm print:hidden"
            style="display: none;">
            <div @click.outside="openChildrenModal = false"
                class="bg-white rounded-xl shadow-2xl w-full max-w-xl overflow-hidden border border-gray-200">
                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="font-bold text-base" x-text="'Linked Children — ' + currentParent.name"></h3>
                    <button @click="openChildrenModal = false"
                        class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        <template x-for="student in (currentParent.students || [])">
                            <div
                                class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <div>
                                    <p class="font-bold text-sm text-gray-800"
                                        x-text="student.first_name + ' ' + student.last_name"></p>
                                    <p class="text-xs text-gray-500"
                                        x-text="student.adm_number + ' · ' + student.class_name"></p>
                                </div>
                                <form :action="'/admin/parents/' + currentParent.id + '/unlink-child/' + student.id"
                                    method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                        class="px-3 py-1 bg-rose-50 text-rose-600 rounded text-xs font-bold hover:bg-rose-100">Unlink</button>
                                </form>
                            </div>
                        </template>
                        <p x-show="!currentParent.students || currentParent.students.length === 0"
                            class="text-xs text-gray-400 text-center py-4">No children linked to this parent yet.</p>
                    </div>

                    <form :action="'/admin/parents/' + currentParent.id + '/link-child'" method="POST"
                        class="pt-4 border-t border-gray-200 flex space-x-2">
                        @csrf
                        <select name="student_id" required class="flex-1 border-gray-300 rounded-lg text-sm">
                            <option value="">Select student to link...</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}
                                    ({{ $student->adm_number }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Link</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
@endsection