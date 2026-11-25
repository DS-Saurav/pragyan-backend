@extends('layouts.admin')

@section('content')
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>

    @use('App\Models\Complaint', 'C')

    @php
        $who = fn($u) => $u ? $u->name ?? ($u->username ?? 'User #' . $u->id) : '—';
        $statusBadge = [
            'open' => 'bg-rose-100 text-rose-800',
            'investigating' => 'bg-amber-100 text-amber-800',
            'resolved' => 'bg-emerald-100 text-emerald-800',
            'dismissed' => 'bg-gray-200 text-gray-700',
        ];
        $severityBadge = [
            'high' => 'bg-rose-100 text-rose-800',
            'medium' => 'bg-amber-100 text-amber-800',
            'low' => 'bg-sky-100 text-sky-800',
        ];
        $tabs = [
            'all' => ['ALL', 'bg-slate-900 text-white'],
            'open' => ['OPEN', 'bg-rose-500 text-white'],
            'investigating' => ['INVESTIGATING', 'bg-amber-500 text-white'],
            'resolved' => ['RESOLVED', 'bg-emerald-600 text-white'],
            'dismissed' => ['DISMISSED', 'bg-gray-500 text-white'],
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

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-5 gap-4">
            <h2 class="text-xl font-bold text-gray-900"><i class="bi bi-megaphone"></i> Complaints Management</h2>
            @can('complaints.create')
                <button type="button" id="openCreateBtn"
                    class="no-print px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">+
                    File Complaint</button>
            @endcan
        </div>

        <!-- Stat cards -->
        <div class="no-print grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            @foreach ([['Open', $stats['open'], 'bg-rose-600'], ['Investigating', $stats['investigating'], 'bg-amber-500'], ['Escalated', $stats['escalated'], 'bg-purple-700'], ['Overdue', $stats['overdue'], 'bg-red-800'], ['Resolved this month', $stats['resolved_month'], 'bg-emerald-600']] as [$label, $value, $color])
                <div class="{{ $color }} text-white p-4 rounded-xl shadow-sm">
                    <p class="text-xl font-bold">{{ $value }}</p>
                    <p class="text-[11px] uppercase tracking-wider opacity-80 font-semibold mt-1">{{ $label }}</p>
                </div>
            @endforeach
        </div>

        <!-- Status tabs -->
        <div class="no-print flex flex-wrap gap-2 mb-5">
            @foreach ($tabs as $key => [$label, $activeClass])
                <a href="{{ route('admin.complaints.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition {{ $tab === $key ? $activeClass . ' shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Toolbar -->
            <div class="no-print flex flex-col lg:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.complaints.csv', request()->query()) }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-folder2-open"></i>
                        CSV</a>
                    <button type="button" onclick="window.print()" title="Choose 'Save as PDF' in the print dialog"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-filetype-pdf"></i>
                        PDF</button>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md shadow"><i class="bi bi-printer"></i>
                        PRINT</button>
                </div>

                <form method="GET" action="{{ route('admin.complaints.index') }}"
                    class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <select name="per_page" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        @foreach ([10, 25, 50, 100] as $n)
                            <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} / page
                            </option>
                        @endforeach
                    </select>

                    <select name="severity" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All severities</option>
                        @foreach (C::SEVERITIES as $v => $l)
                            <option value="{{ $v }}" @selected(request('severity') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>

                    <select name="against" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">Against: all</option>
                        @foreach (C::AGAINST as $v => $l)
                            <option value="{{ $v }}" @selected(request('against') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>

                    @if ($isManager)
                        <select name="assigned" onchange="this.form.submit()"
                            class="text-xs border border-gray-300 rounded-lg p-2">
                            <option value="">Anyone</option>
                            <option value="none" @selected(request('assigned') === 'none')>Unassigned</option>
                            @foreach ($staff as $s)
                                <option value="{{ $s->id }}" @selected((string) request('assigned') === (string) $s->id)>{{ $who($s) }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="Filed from">
                    <input type="date" name="to" value="{{ request('to') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="Filed until">

                    <label class="flex items-center gap-1 text-xs font-semibold text-gray-700">
                        <input type="checkbox" name="escalated" value="1" onchange="this.form.submit()"
                            @checked(request()->boolean('escalated'))>
                        Escalated
                    </label>
                    <label class="flex items-center gap-1 text-xs font-semibold text-gray-700">
                        <input type="checkbox" name="overdue" value="1" onchange="this.form.submit()"
                            @checked(request()->boolean('overdue'))>
                        Overdue
                    </label>

                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Number, subject, detail, name"
                        class="text-xs border border-gray-300 rounded-lg p-2 w-48 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">Search</button>

                    @if (request()->hasAny(['search', 'severity', 'against', 'assigned', 'overdue', 'escalated', 'from', 'to']))
                        <a href="{{ route('admin.complaints.index', ['tab' => $tab]) }}"
                            class="px-3 py-2 bg-gray-200 text-xs font-bold rounded-lg">Reset</a>
                    @endif
                </form>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Complaint</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Against</th>
                            <th class="px-4 py-3">Filed by</th>
                            <th class="px-4 py-3">Severity</th>
                            @if ($isManager)
                                <th class="px-4 py-3">Assigned to</th>
                            @endif
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last activity</th>
                            <th class="px-4 py-3 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($complaints as $c)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('admin.complaints.show', $c) }}"
                                        class="font-bold text-blue-700 hover:underline font-mono">{{ $c->complaint_no }}</a>
                                </td>
                                <td class="px-4 py-3 font-bold text-gray-900 max-w-xs">
                                    <a href="{{ route('admin.complaints.show', $c) }}"
                                        class="hover:underline">{{ $c->subject }}</a>
                                    @if ($c->is_escalated)
                                        <span
                                            class="ml-1 px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 text-[10px] font-bold uppercase">Escalated</span>
                                    @endif
                                    @if ($c->isOverdue())
                                        <span
                                            class="ml-1 px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-bold uppercase">Overdue</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-gray-100 text-gray-700">{{ C::AGAINST[$c->against] ?? $c->against }}</span>
                                    @if ($c->against_detail)
                                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $c->against_detail }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="font-semibold {{ $c->hidesFilerFrom(auth()->id()) ? 'text-gray-400 italic' : 'text-gray-900' }}">
                                        {{ $c->filerName() }}</div>
                                    @unless ($c->hidesFilerFrom(auth()->id()))
                                        <div class="text-[11px] text-gray-400">{{ $roles[$c->filed_by] ?? '' }}@if ($c->is_anonymous)
                                                · filed anonymously
                                            @endif
                                        </div>
                                    @endunless
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $severityBadge[$c->severity] ?? '' }}">{{ $c->severity }}</span>
                                </td>
                                @if ($isManager)
                                    <td class="px-4 py-3 text-gray-700">{{ $who($c->assignee) }}</td>
                                @endif
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $statusBadge[$c->status] ?? '' }}">{{ C::STATUS_LABELS[$c->status] ?? $c->status }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-500"
                                    title="Filed {{ $c->created_at->format('Y-m-d H:i') }}">
                                    {{ ($c->last_activity_at ?? $c->created_at)->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 no-print">
                                    <a href="{{ route('admin.complaints.show', $c) }}" class="p-1 hover:text-indigo-600"
                                        title="Open complaint"><i class="bi bi-eye"></i></a>
                                    @can('complaints.delete')
                                        <form action="{{ route('admin.complaints.destroy', $c) }}" method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Delete complaint {{ $c->complaint_no }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-1 hover:text-rose-600"
                                                title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isManager ? 9 : 8 }}" class="px-4 py-8 text-center text-gray-500">No
                                        complaints found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                    <div>Showing {{ $complaints->firstItem() ?? 0 }} to {{ $complaints->lastItem() ?? 0 }} of
                        {{ $complaints->total() }} entries</div>
                    <div class="no-print">{{ $complaints->links() }}</div>
                </div>
            </div>
        </div>

        <!-- File complaint modal -->
        <div id="createModal" style="display:none;"
            class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 max-h-[92vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <h3 class="text-md font-bold">File a Complaint</h3>
                    <button type="button" class="close-modal text-xl">&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.complaints.store') }}" enctype="multipart/form-data"
                    class="space-y-4">
                    @csrf
                    <input type="hidden" name="_mode" value="create">
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Subject *</label>
                        <input type="text" name="subject" required maxlength="150" value="{{ old('subject') }}"
                            placeholder="Short summary, e.g. School bus arriving late"
                            class="w-full text-xs border rounded-lg p-2.5">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Complaint is about *</label>
                            <select name="against" required class="w-full text-xs border rounded-lg p-2.5">
                                @foreach (C::AGAINST as $v => $l)
                                    <option value="{{ $v }}" @selected(old('against') === $v)>{{ $l }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">How serious? *</label>
                            <select name="severity" required class="w-full text-xs border rounded-lg p-2.5">
                                @foreach (C::SEVERITIES as $v => $l)
                                    <option value="{{ $v }}" @selected(old('severity', 'medium') === $v)>{{ $l }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Who / which (optional)</label>
                            <input type="text" name="against_detail" maxlength="150" value="{{ old('against_detail') }}"
                                placeholder="e.g. Bus 3, Mr. Sharma" class="w-full text-xs border rounded-lg p-2.5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">When did it happen?</label>
                            <input type="date" name="incident_date" max="{{ now()->toDateString() }}"
                                value="{{ old('incident_date') }}" class="w-full text-xs border rounded-lg p-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">What happened? *</label>
                        <textarea name="description" rows="5" required maxlength="5000"
                            placeholder="Describe what happened, who was involved and what you would like done."
                            class="w-full text-xs border rounded-lg p-2.5">{{ old('description') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Evidence (optional)</label>
                        <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                            class="w-full text-xs">
                        <p class="text-[11px] text-gray-400 mt-1">Photo, PDF or Word, up to 5 MB.</p>
                    </div>
                    <label class="flex items-start gap-2 bg-gray-50 border rounded-lg p-3 text-xs text-gray-700">
                        <input type="checkbox" name="is_anonymous" value="1" class="mt-0.5"
                            @checked(old('is_anonymous'))>
                        <span><b>File anonymously.</b> Staff and other users will not see your name on this complaint or in its
                            replies. You will still see it under your own account, so you can follow progress.</span>
                    </label>
                    <div class="flex justify-end space-x-2 pt-1">
                        <button type="button"
                            class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Submit
                            Complaint</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            (function() {
                var modal = document.getElementById('createModal');
                var open = function() {
                    modal.style.display = 'flex';
                };
                var close = function() {
                    modal.style.display = 'none';
                };

                document.addEventListener('click', function(e) {
                    if (e.target.closest('#openCreateBtn')) return open();
                    if (e.target.closest('.close-modal') || e.target === modal) close();
                });

                // Reopen the form with the user's input if validation failed
                @if ($errors->any() && old('_mode'))
                    open();
                @endif
            })();
        </script>
    @endsection
