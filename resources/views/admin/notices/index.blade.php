@extends('layouts.admin')

@section('content')
    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{ showModal: false }">

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
                        📢 School Notices
                    </h3>
                    <p class="text-xs text-gray-500">Manage school announcements and broadcast notices to portals or public
                        CMS.</p>
                </div>
                <button @click="showModal = true"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                    + Post Notice
                </button>
            </div>

            <!-- Filter Tabs -->
            <div
                class="grid grid-cols-3 gap-2 mb-6 bg-slate-900 p-1.5 rounded-xl text-center text-xs font-bold uppercase tracking-wider">
                <a href="{{ route('admin.notices.index', ['tab' => 'all']) }}"
                    class="py-2.5 rounded-lg transition {{ $tab === 'all' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                    All ({{ $totalCount }})
                </a>
                <a href="{{ route('admin.notices.index', ['tab' => 'published']) }}"
                    class="py-2.5 rounded-lg transition {{ $tab === 'published' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                    Published ({{ $publishedCount }})
                </a>
                <a href="{{ route('admin.notices.index', ['tab' => 'draft']) }}"
                    class="py-2.5 rounded-lg transition {{ $tab === 'draft' ? 'bg-amber-600 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                    Draft ({{ $draftCount }})
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

            <!-- Notices Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Title</th>
                            <th class="px-4 py-3">Audience</th>
                            <th class="px-4 py-3">Posted By</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($notices as $notice)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 font-mono text-gray-600">{{ $notice->date }}</td>
                                <td class="px-4 py-3 font-bold text-gray-900">{{ $notice->title }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded font-bold uppercase text-[10px]">
                                        {{ $notice->audience }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $notice->posted_by }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2 py-0.5 rounded font-bold uppercase text-[10px] {{ $notice->status === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $notice->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <form action="{{ route('admin.notices.destroy', $notice->id) }}" method="POST"
                                        class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Delete this notice?')"
                                            class="text-rose-500 hover:text-rose-700 font-bold" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-gray-500 font-medium">No notices found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add School Notice Modal -->
        <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm"
            style="display: none;">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden border border-gray-100">

                <!-- Modal Header -->
                <div class="bg-slate-900 text-white px-6 py-4 flex justify-between items-center">
                    <h3 class="text-sm font-bold flex items-center gap-2">
                        📢 Add — School Notices
                    </h3>
                    <button @click="showModal = false"
                        class="text-gray-400 hover:text-white text-lg font-bold">&times;</button>
                </div>

                <!-- Modal Form -->
                <form action="{{ route('admin.notices.store') }}" method="POST" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Title
                            *</label>
                        <input type="text" name="title" required
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Notice
                            Body</label>
                        <textarea name="content" rows="4"
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Audience</label>
                            <select name="audience" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium">
                                <option value="all">all</option>
                                <option value="teachers">teachers</option>
                                <option value="parents">parents</option>
                                <option value="students">students</option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Date</label>
                            <input type="date" name="date" value="{{ date('Y-m-d') }}" required
                                class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-medium">
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-[11px] font-bold uppercase tracking-wider text-gray-700 mb-1">Status</label>
                        <select name="status" required
                            class="w-full text-xs border border-gray-300 rounded-lg p-2.5 bg-white font-medium">
                            <option value="published">published</option>
                            <option value="draft">draft</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" @click="showModal = false"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">Cancel</button>
                        <button type="submit"
                            class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
