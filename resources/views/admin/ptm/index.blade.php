@extends('layouts.admin')

@section('content')
    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{
        showAddModal: false,
        showEditModal: false,
        editData: {
            id: null,
            title: '',
            school_class_id: '',
            meeting_date: '',
            time_slot: '',
            venue: '',
            agenda: '',
            status: 'scheduled'
        },
        openEdit(meeting) {
            this.editData = {
                id: meeting.id,
                title: meeting.title,
                school_class_id: meeting.school_class_id ?? '',
                meeting_date: meeting.meeting_date,
                time_slot: meeting.time_slot,
                venue: meeting.venue,
                agenda: meeting.agenda ?? '',
                status: meeting.status
            };
            this.showEditModal = true;
        }
    }">

        @if (session('success'))
            <div
                class="mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Header Row -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <i class="bi bi-people"></i> Parent-Teacher Meetings (PTM)
                    </h3>
                    <p class="text-xs text-gray-500">Schedule PTM sessions, assign target classes, venues, and track
                        progress.</p>
                </div>
                <button @click="showAddModal = true"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                    + Schedule PTM
                </button>
            </div>

            <!-- Filter Tabs -->
            <div
                class="grid grid-cols-4 gap-2 mb-6 bg-slate-900 p-1.5 rounded-xl text-center text-xs font-bold uppercase tracking-wider">
                <a href="{{ route('admin.ptm.index', ['tab' => 'all']) }}"
                    class="py-2.5 rounded-lg transition {{ $tab === 'all' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                    All ({{ $totalCount }})
                </a>
                <a href="{{ route('admin.ptm.index', ['tab' => 'scheduled']) }}"
                    class="py-2.5 rounded-lg transition {{ $tab === 'scheduled' ? 'bg-amber-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                    Scheduled ({{ $scheduledCount }})
                </a>
                <a href="{{ route('admin.ptm.index', ['tab' => 'completed']) }}"
                    class="py-2.5 rounded-lg transition {{ $tab === 'completed' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                    Completed ({{ $completedCount }})
                </a>
                <a href="{{ route('admin.ptm.index', ['tab' => 'cancelled']) }}"
                    class="py-2.5 rounded-lg transition {{ $tab === 'cancelled' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                    Cancelled ({{ $cancelledCount }})
                </a>
            </div>

            <!-- Action Bar -->
            <div class="flex justify-between items-center mb-4 text-xs">
                <div class="flex gap-2">
                    <button onclick="window.print();"
                        class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded shadow-sm">CSV</button>
                    <button onclick="window.print();"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded shadow-sm">PDF</button>
                    <button onclick="window.print();"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded shadow-sm">PRINT</button>
                </div>
            </div>

            <!-- PTM Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Date & Time</th>
                            <th class="px-4 py-3">Title / Agenda</th>
                            <th class="px-4 py-3">Target Class</th>
                            <th class="px-4 py-3">Venue</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($meetings as $meeting)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-mono text-gray-700">
                                    <div class="font-bold text-gray-900">{{ $meeting->meeting_date }}</div>
                                    <div class="text-[10px] text-gray-500">{{ $meeting->time_slot }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ $meeting->title }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $meeting->agenda ?? '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded font-bold uppercase text-[10px]">
                                        {{ $meeting->schoolClass->name ?? 'All Classes' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-700">{{ $meeting->venue }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2 py-0.5 rounded font-bold uppercase text-[10px] 
                                    {{ $meeting->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($meeting->status === 'cancelled' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                        {{ $meeting->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button @click="openEdit({{ json_encode($meeting) }})"
                                        class="text-indigo-600 hover:text-indigo-900 font-bold" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <form action="{{ route('admin.ptm.destroy', $meeting->id) }}" method="POST"
                                        class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Delete this PTM record?')"
                                            class="text-rose-500 hover:text-rose-700 font-bold" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-gray-500 font-medium">No PTM meetings
                                    scheduled.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Schedule PTM Modal (Create) -->
        <div x-show="showAddModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
            style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100">

                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="text-sm font-bold flex items-center gap-2">
                        <i class="bi bi-people"></i> Schedule — Parent-Teacher Meeting
                    </h3>
                    <button @click="showAddModal = false"
                        class="text-gray-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form action="{{ route('admin.ptm.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Meeting Title
                            *</label>
                        <input type="text" name="title" required placeholder="e.g. 1st Term Academic Progress Review"
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Target
                                Class</label>
                            <select name="school_class_id"
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium">
                                <option value="">All Classes</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Date
                                *</label>
                            <input type="date" name="meeting_date" value="{{ date('Y-m-d') }}" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Time Slot
                                *</label>
                            <input type="text" name="time_slot" required placeholder="e.g. 10:00 AM - 01:00 PM"
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Venue
                                *</label>
                            <input type="text" name="venue" required value="Classroom / Assembly Hall"
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Agenda /
                            Details</label>
                        <textarea name="agenda" rows="3" placeholder="Enter meeting agenda or guidelines for parents..."
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium"></textarea>
                    </div>

                    <div>
                        <label
                            class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Status</label>
                        <select name="status" required
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium">
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" @click="showAddModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">Schedule
                            PTM</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit PTM Modal -->
        <div x-show="showEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
            style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100">

                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="text-sm font-bold flex items-center gap-2">
                        <i class="bi bi-pencil"></i> Edit / Update Status — Parent-Teacher Meeting
                    </h3>
                    <button @click="showEditModal = false"
                        class="text-gray-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form :action="'{{ url('admin/ptm') }}/' + editData.id" method="POST" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Meeting
                            Title *</label>
                        <input type="text" name="title" x-model="editData.title" required
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Target
                                Class</label>
                            <select name="school_class_id" x-model="editData.school_class_id"
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium">
                                <option value="">All Classes</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Date
                                *</label>
                            <input type="date" name="meeting_date" x-model="editData.meeting_date" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Time
                                Slot *</label>
                            <input type="text" name="time_slot" x-model="editData.time_slot" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Venue
                                *</label>
                            <input type="text" name="venue" x-model="editData.venue" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Agenda /
                            Details</label>
                        <textarea name="agenda" rows="3" x-model="editData.agenda"
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium"></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Status
                            *</label>
                        <select name="status" x-model="editData.status" required
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium">
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" @click="showEditModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow">Update
                            Meeting</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
