@extends('layouts.admin')

@section('content')
    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{
        showAddModal: false,
        showEditModal: false,
        editData: {
            id: null,
            title: '',
            date: '',
            type: 'event',
            description: ''
        },
        openEdit(event) {
            this.editData = {
                id: event.id,
                title: event.title,
                date: event.date,
                type: event.type,
                description: event.description ?? ''
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
                        📅 School Calendar
                    </h3>
                    <p class="text-xs text-gray-500">Manage school holidays, internal events, and examination schedules.</p>
                </div>
                <button @click="showAddModal = true"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                    + Add Event
                </button>
            </div>

            <!-- Chevron / Arrow Filter Tabs matching design -->
            <div class="grid grid-cols-4 gap-1 mb-6 text-xs font-bold uppercase tracking-wider text-center">
                <a href="{{ route('admin.calendar.index', ['tab' => 'all']) }}"
                    class="py-3 px-4 transition {{ $tab === 'all' ? 'bg-slate-900 text-white shadow' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }} rounded-l-xl">
                    All ({{ $totalCount }})
                </a>
                <a href="{{ route('admin.calendar.index', ['tab' => 'holidays']) }}"
                    class="py-3 px-4 transition {{ $tab === 'holidays' ? 'bg-rose-600 text-white shadow' : 'bg-rose-500/80 text-white hover:bg-rose-600' }}">
                    Holidays ({{ $holidayCount }})
                </a>
                <a href="{{ route('admin.calendar.index', ['tab' => 'events']) }}"
                    class="py-3 px-4 transition {{ $tab === 'events' ? 'bg-cyan-600 text-white shadow' : 'bg-cyan-500/80 text-white hover:bg-cyan-600' }}">
                    Events ({{ $eventCount }})
                </a>
                <a href="{{ route('admin.calendar.index', ['tab' => 'exams']) }}"
                    class="py-3 px-4 transition {{ $tab === 'exams' ? 'bg-purple-600 text-white shadow' : 'bg-purple-600/80 text-white hover:bg-purple-600' }} rounded-r-xl">
                    Exams ({{ $examCount }})
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

            <!-- Calendar Events Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3 w-32">Date</th>
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3 w-32">Type</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($events as $event)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-mono text-gray-600 font-semibold">{{ $event->date }}</td>
                                <td class="px-4 py-3 font-bold text-gray-900">{{ $event->title }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-0.5 rounded font-bold uppercase text-[10px] 
                                    {{ $event->type === 'holiday' ? 'bg-rose-100 text-rose-800' : ($event->type === 'exam' ? 'bg-purple-100 text-purple-800' : 'bg-cyan-100 text-cyan-800') }}">
                                        {{ $event->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $event->description ?? '-' }}</td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button @click="openEdit({{ json_encode($event) }})"
                                        class="text-indigo-600 hover:text-indigo-900 font-bold" title="Edit">✏️</button>
                                    <form action="{{ route('admin.calendar.destroy', $event->id) }}" method="POST"
                                        class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Delete this event?')"
                                            class="text-rose-500 hover:text-rose-700 font-bold" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-6 text-center text-gray-500 font-medium">No calendar events
                                    found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Event Modal -->
        <div x-show="showAddModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
            style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100">

                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="text-sm font-bold flex items-center gap-2">
                        📅 Add — School Calendar Event
                    </h3>
                    <button @click="showAddModal = false"
                        class="text-gray-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form action="{{ route('admin.calendar.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Event Title
                            *</label>
                        <input type="text" name="title" required
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Date
                                *</label>
                            <input type="date" name="date" value="{{ date('Y-m-d') }}" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Type
                                *</label>
                            <select name="type" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium">
                                <option value="holiday">Holiday</option>
                                <option value="event">Event</option>
                                <option value="exam">Exam</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3"
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" @click="showAddModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">Save
                            Event</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Event Modal -->
        <div x-show="showEditModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
            style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100">

                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="text-sm font-bold flex items-center gap-2">
                        ✏️ Edit — School Calendar Event
                    </h3>
                    <button @click="showEditModal = false"
                        class="text-gray-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <form :action="'{{ url('admin/calendar') }}/' + editData.id" method="POST" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Event Title
                            *</label>
                        <input type="text" name="title" x-model="editData.title" required
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Date
                                *</label>
                            <input type="date" name="date" x-model="editData.date" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Type
                                *</label>
                            <select name="type" x-model="editData.type" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium">
                                <option value="holiday">Holiday</option>
                                <option value="event">Event</option>
                                <option value="exam">Exam</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" x-model="editData.description"
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" @click="showEditModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow">Update
                            Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
