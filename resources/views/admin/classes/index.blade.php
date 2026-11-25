@extends('layouts.admin')

@section('content')
    <div x-data="{ openModal: false }" class="space-y-6">
        
        <!-- Page Header & Add Button -->
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <span class="text-xl"><i class="bi bi-building"></i></span>
                <h3 class="text-xl font-bold text-gray-800">Classes & Sections</h3>
            </div>
            <!-- Trigger Modal Button -->
            <button @click="openModal = true" class="px-4 py-2 bg-emerald-600 text-white font-semibold text-sm rounded-lg hover:bg-emerald-700 transition shadow-sm flex items-center space-x-1">
                <span>+ Add Class</span>
            </button>
        </div>

        <!-- Success Message -->
        @if(session('success'))
            <div class="p-4 bg-green-100 text-green-700 rounded-lg text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        <!-- Add Class Modal / Popup Backdrop -->
        <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" style="display: none;">
            <!-- Modal Box -->
            <div @click.outside="openModal = false" class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-200">
                
                <!-- Modal Header -->
                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <div class="flex items-center space-x-2">
                        <span><i class="bi bi-building"></i></span>
                        <h3 class="font-bold text-base">Add Class</h3>
                    </div>
                    <button @click="openModal = false" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <!-- Modal Form -->
                <form action="{{ route('admin.classes.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Class Name -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Class Name *</label>
                            <input type="text" name="name" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="e.g. Class 1">
                            <p class="text-[11px] text-gray-400 mt-1">Class + Section + Year must be unique</p>
                        </div>

                        <!-- Section -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Section *</label>
                            <select name="section" required class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>

                        <!-- Academic Year -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Academic Year *</label>
                            <input type="text" name="academic_year" value="2082-2083" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="2082-2083">
                        </div>

                        <!-- Capacity -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Capacity</label>
                            <input type="number" name="capacity" value="35" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="35">
                        </div>

                        <!-- Location / Room -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Location / Room</label>
                            <input type="text" name="room" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" placeholder="e.g. Room 101">
                        </div>

                        <!-- Class Teacher -->
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Class Teacher</label>
                            <select name="teacher" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="">Select teacher...</option>
                                <option value="Teacher 1">Teacher 1</option>
                                <option value="Teacher 2">Teacher 2</option>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Status</label>
                            <select name="status" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Modal Footer Buttons -->
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 mt-6">
                        <button type="button" @click="openModal = false" class="px-4 py-2 bg-gray-100 text-gray-700 font-medium text-sm rounded-lg hover:bg-gray-200 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 text-white font-medium text-sm rounded-lg hover:bg-emerald-700 transition shadow-sm">
                            Add Class
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            
            <!-- Table Toolbar -->
            <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-4 bg-gray-50/50">
                <div class="flex items-center space-x-2 text-sm">
                    <button class="px-3 py-1.5 bg-emerald-600 text-white rounded font-medium text-xs hover:bg-emerald-700 shadow-sm">CSV</button>
                    <button class="px-3 py-1.5 bg-rose-600 text-white rounded font-medium text-xs hover:bg-rose-700 shadow-sm">PDF</button>
                    <button class="px-3 py-1.5 bg-blue-600 text-white rounded font-medium text-xs hover:bg-blue-700 shadow-sm">PRINT</button>
                    <div class="flex items-center space-x-1 text-xs text-gray-500 pl-4">
                        <span>Show</span>
                        <select class="border border-gray-300 rounded px-2 py-1 text-xs">
                            <option>10</option>
                            <option>25</option>
                            <option>50</option>
                        </select>
                        <span>entries</span>
                    </div>
                </div>
                <div>
                    <input type="text" placeholder="Search..." class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-900 text-white uppercase text-xs tracking-wider">
                            <th class="p-4">Class</th>
                            <th class="p-4">Academic Year</th>
                            <th class="p-4">Location / Room</th>
                            <th class="p-4">Capacity</th>
                            <th class="p-4">Strength</th>
                            <th class="p-4">Class Teacher</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($classes as $index => $class)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-4 font-bold text-gray-800">
                                    {{ $class->name }} - {{ $class->section }}
                                </td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 bg-sky-50 text-sky-700 rounded-md text-xs font-semibold border border-sky-100">
                                        2082-2083
                                    </span>
                                </td>
                                <td class="p-4 text-gray-600">Room 10{{ $index + 1 }}</td>
                                <td class="p-4 text-gray-600">35</td>
                                <td class="p-4 text-gray-600 font-medium">0 / 35</td>
                                <td class="p-4 text-gray-600">Assigned Teacher</td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-bold">
                                        ACTIVE
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="inline-flex items-center space-x-2">
                                        <button class="p-1.5 bg-sky-50 text-sky-600 rounded hover:bg-sky-100 text-xs" title="View Details"><i class="bi bi-calendar3"></i></button>
                                        <button class="p-1.5 bg-emerald-50 text-emerald-600 rounded hover:bg-emerald-100 text-xs" title="Manage Students"><i class="bi bi-people"></i></button>
                                        <form action="{{ route('admin.classes.destroy', $class->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete this class?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 bg-rose-50 text-rose-600 rounded hover:bg-rose-100 text-xs" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-400">No classes found in the system yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Footer Pagination -->
            <div class="p-4 border-t border-gray-200 flex justify-between items-center text-xs text-gray-500 bg-gray-50/50">
                <span>Showing 1 to {{ count($classes) }} of {{ count($classes) }} entries</span>
                <div class="flex space-x-1">
                    <button class="px-3 py-1 bg-white border border-gray-300 rounded text-gray-600 hover:bg-gray-100 disabled:opacity-50">Previous</button>
                    <button class="px-3 py-1 bg-indigo-600 text-white rounded font-bold">1</button>
                    <button class="px-3 py-1 bg-white border border-gray-300 rounded text-gray-600 hover:bg-gray-100">Next</button>
                </div>
            </div>

        </div>

    </div>
@endsection