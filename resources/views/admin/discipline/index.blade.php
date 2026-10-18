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
        $sevBadge = [
            'low' => 'bg-blue-100 text-blue-800',
            'medium' => 'bg-amber-100 text-amber-800',
            'high' => 'bg-rose-100 text-rose-800',
            'critical' => 'bg-red-700 text-white',
        ];
        $statusBadge = [
            'open' => 'bg-rose-100 text-rose-800',
            'in_review' => 'bg-amber-100 text-amber-800',
            'closed' => 'bg-gray-200 text-gray-700',
        ];
        $statusLabels = ['open' => 'Open', 'in_review' => 'In Review', 'closed' => 'Closed'];
        $tabs = [
            'all' => ['ALL', 'bg-slate-900 text-white'],
            'open' => ['OPEN', 'bg-rose-500 text-white'],
            'in_review' => ['IN REVIEW', 'bg-amber-500 text-white'],
            'closed' => ['CLOSED', 'bg-emerald-600 text-white'],
        ];
        $today = now()->toDateString();
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
            <h2 class="text-xl font-bold text-gray-900">🛡️ Discipline Management</h2>
            @can('discipline.report')
                <button type="button" id="openCreateBtn"
                    class="no-print px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow">+
                    Report Incident</button>
            @endcan
        </div>

        <!-- Stat cards -->
        <div class="no-print grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            @foreach ([['Active cases', $stats['active'], 'bg-slate-900'], ['Serious (high / critical)', $stats['serious'], 'bg-rose-600'], ['Parent not notified', $stats['not_notified'], 'bg-amber-600'], ['Follow-ups overdue', $stats['overdue'], 'bg-purple-600'], ['Incidents this month', $stats['month'], 'bg-sky-600']] as [$label, $value, $color])
                <div class="{{ $color }} text-white p-4 rounded-xl shadow-sm">
                    <p class="text-2xl font-bold">{{ $value }}</p>
                    <p class="text-[11px] uppercase tracking-wider opacity-80 font-semibold mt-1">{{ $label }}</p>
                </div>
            @endforeach
        </div>

        <!-- Status tabs -->
        <div class="no-print flex flex-wrap gap-2 mb-5">
            @foreach ($tabs as $key => [$label, $activeClass])
                <a href="{{ route('admin.discipline.index', array_merge(request()->except('page', 'tab'), ['tab' => $key])) }}"
                    class="px-4 py-2 rounded-lg text-xs font-bold transition {{ $tab === $key ? $activeClass . ' shadow' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-100' }}">
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Toolbar -->
            <div class="no-print flex flex-col lg:flex-row justify-between gap-4 mb-5 border-b pb-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.discipline.csv', request()->query()) }}"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-md shadow">📂
                        CSV</a>
                    <button type="button" onclick="window.print()" title="Choose 'Save as PDF' in the print dialog"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-md shadow">📄
                        PDF</button>
                    <button type="button" onclick="window.print()"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-md shadow">🖨️
                        PRINT</button>
                </div>

                <form method="GET" action="{{ route('admin.discipline.index') }}"
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

                    <select name="severity" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All severity</option>
                        @foreach (\App\Models\DisciplineIncident::SEVERITIES as $v => $l)
                            <option value="{{ $v }}" @selected(request('severity') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>

                    <select name="category" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2">
                        <option value="">All categories</option>
                        @foreach (\App\Models\DisciplineIncident::CATEGORIES as $v => $l)
                            <option value="{{ $v }}" @selected(request('category') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="From date">
                    <input type="date" name="to" value="{{ request('to') }}" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2" title="To date">

                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search student or incident..."
                        class="text-xs border border-gray-300 rounded-lg p-2 w-48 focus:ring-2 focus:ring-indigo-500">
                    <button type="submit"
                        class="px-3 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg hover:bg-slate-800">Search</button>

                    @if (request()->hasAny(['search', 'class_id', 'severity', 'category', 'from', 'to']))
                        <a href="{{ route('admin.discipline.index', ['tab' => $tab]) }}"
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
                            <th class="px-4 py-3">Student</th>
                            <th class="px-4 py-3">Incident</th>
                            <th class="px-4 py-3">Severity</th>
                            <th class="px-4 py-3">Action Taken</th>
                            <th class="px-4 py-3">Parent Notified</th>
                            <th class="px-4 py-3">Follow-up</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($incidents as $inc)
                            @php
                                $stu = $inc->student;
                                $className = $classNames[$stu->school_class_id ?? 0] ?? '—';
                                $studentName = \App\Services\ResultService::studentName($stu);
                                $times = $history[$inc->student_id] ?? 1;
                                $overdue =
                                    $inc->status !== 'closed' &&
                                    $inc->follow_up_date &&
                                    $inc->follow_up_date->toDateString() < $today;
                                $reporter = $inc->reporter
                                    ? trim($inc->reporter->first_name . ' ' . $inc->reporter->last_name)
                                    : '';

                                $payload = [
                                    'id' => $inc->id,
                                    'student_id' => $inc->student_id,
                                    'class_id' => $stu->school_class_id ?? null,
                                    'reported_by' => $inc->reported_by,
                                    'incident_date' => $inc->incident_date->format('Y-m-d'),
                                    'category' => $inc->category,
                                    'severity' => $inc->severity,
                                    'title' => $inc->title,
                                    'description' => $inc->description,
                                    'location' => $inc->location,
                                    'witnesses' => $inc->witnesses,
                                    'action_type' => $inc->action_type,
                                    'action_taken' => $inc->action_taken,
                                    'parent_notified' => $inc->parent_notified,
                                    'parent_notified_on' => $inc->parent_notified_on?->format('Y-m-d'),
                                    'notified_via' => $inc->notified_via,
                                    'follow_up_date' => $inc->follow_up_date?->format('Y-m-d'),
                                    'status' => $inc->status,
                                    'resolution' => $inc->resolution,
                                    // display only
                                    'student_name' => $studentName,
                                    'class_name' => $className,
                                    'reporter_name' => $reporter,
                                    'category_label' =>
                                        \App\Models\DisciplineIncident::CATEGORIES[$inc->category] ?? $inc->category,
                                    'severity_label' =>
                                        \App\Models\DisciplineIncident::SEVERITIES[$inc->severity] ?? $inc->severity,
                                    'action_label' => \App\Models\DisciplineIncident::ACTIONS[$inc->action_type] ?? '',
                                    'notified_label' =>
                                        \App\Models\DisciplineIncident::NOTIFY_METHODS[$inc->notified_via] ?? '',
                                    'status_label' => $statusLabels[$inc->status] ?? $inc->status,
                                    'history' => $times,
                                    'closed_at' => $inc->closed_at?->format('Y-m-d'),
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition" data-incident="{{ json_encode($payload) }}">
                                <td class="px-4 py-3 whitespace-nowrap text-gray-800">
                                    {{ $inc->incident_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ $studentName }}
                                        @if ($times > 1)
                                            <span
                                                class="ml-1 px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-bold"
                                                title="{{ $times }} incidents on record">×{{ $times }}</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-gray-400">{{ $className }}@if (\App\Services\ResultService::roll($stu))
                                            · Roll {{ \App\Services\ResultService::roll($stu) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    <div class="text-gray-900 font-medium">{{ $inc->title }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $payload['category_label'] }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $sevBadge[$inc->severity] ?? '' }}">{{ $payload['severity_label'] }}</span>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    @if ($inc->action_type || $inc->action_taken)
                                        <div class="font-semibold text-gray-800">
                                            {{ $payload['action_label'] ?: 'Action recorded' }}</div>
                                        @if ($inc->action_taken)
                                            <div class="text-[11px] text-gray-500" title="{{ $inc->action_taken }}">
                                                {{ \Illuminate\Support\Str::limit($inc->action_taken, 50) }}</div>
                                        @endif
                                    @else
                                        <span class="text-gray-400 italic">No action yet</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($inc->parent_notified)
                                        <span
                                            class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">YES</span>
                                        <div class="text-[11px] text-gray-400 mt-0.5">
                                            {{ $inc->parent_notified_on?->format('Y-m-d') }}{{ $payload['notified_label'] ? ' · ' . $payload['notified_label'] : '' }}
                                        </div>
                                    @else
                                        <span
                                            class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-200 text-gray-700">NO</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($inc->follow_up_date)
                                        <div class="{{ $overdue ? 'text-rose-600 font-bold' : 'text-gray-700' }}">
                                            {{ $inc->follow_up_date->format('Y-m-d') }}</div>
                                        @if ($overdue)
                                            <div class="text-[10px] text-rose-600 font-bold uppercase">Overdue</div>
                                        @endif
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase whitespace-nowrap {{ $statusBadge[$inc->status] ?? '' }}">{{ $payload['status_label'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-1 no-print">
                                    <button type="button" class="view-btn p-1 hover:text-indigo-600"
                                        title="View details">👁️</button>

                                    @can('discipline.manage')
                                        @unless ($inc->parent_notified)
                                            <form action="{{ route('admin.discipline.notify', $inc) }}" method="POST"
                                                class="inline" onsubmit="return confirm('Mark the parent as notified today?');">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="p-1 hover:text-emerald-600"
                                                    title="Mark parent notified">📞</button>
                                            </form>
                                        @endunless

                                        @if ($inc->status !== 'closed')
                                            <button type="button" class="close-btn p-1 hover:text-emerald-600"
                                                title="Close case">✅</button>
                                        @endif
                                    @endcan

                                    <a href="{{ route('admin.discipline.notice', $inc) }}" target="_blank"
                                        class="p-1 hover:text-blue-600" title="Print parent notice">🖨️</a>

                                    @can('discipline.manage')
                                        <button type="button" class="edit-btn p-1 hover:text-indigo-600"
                                            title="Edit">✏️</button>
                                    @endcan

                                    @can('discipline.delete')
                                        <form action="{{ route('admin.discipline.destroy', $inc) }}" method="POST"
                                            class="inline" onsubmit="return confirm('Delete this incident record?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-1 hover:text-rose-600"
                                                title="Delete">🗑️</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">No incidents found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-col md:flex-row justify-between items-center gap-3 text-xs text-gray-500">
                    <div>Showing {{ $incidents->firstItem() ?? 0 }} to {{ $incidents->lastItem() ?? 0 }} of
                        {{ $incidents->total() }} entries</div>
                    <div class="no-print">{{ $incidents->links() }}</div>
                </div>
            </div>
        </div>

        <!-- Report / Edit modal -->
        <div id="formModal" style="display:none;"
            class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl p-6 max-h-[92vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <h3 class="text-md font-bold" id="formTitle">Report Incident</h3>
                    <button type="button" class="close-modal text-xl" data-target="formModal">&times;</button>
                </div>

                <form id="incidentForm" method="POST" action="{{ route('admin.discipline.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="PUT" disabled>
                    <input type="hidden" name="_mode" id="formMode" value="create">
                    <input type="hidden" name="_edit_id" id="formEditId" value="">

                    <!-- Who & when -->
                    <div>
                        <p class="text-[11px] font-bold uppercase text-indigo-600 mb-2">1 · Who and when</p>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold uppercase mb-1">Class *</label>
                                <select name="class_id" id="fClass" required
                                    class="w-full text-xs border rounded-lg p-2.5">
                                    <option value="">Select class...</option>
                                    @foreach ($classes as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold uppercase mb-1">Student *</label>
                                <select name="student_id" id="fStudent" required
                                    class="w-full text-xs border rounded-lg p-2.5">
                                    <option value="">Select class first...</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Date *</label>
                                <input type="date" name="incident_date" required max="{{ $today }}"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Location</label>
                                <input type="text" name="location" placeholder="e.g. Classroom, Playground"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold uppercase mb-1">Reported by</label>
                                <select name="reported_by" class="w-full text-xs border rounded-lg p-2.5">
                                    <option value="">— Select teacher —</option>
                                    @foreach ($teachers as $t)
                                        <option value="{{ $t->id }}">{{ $t->first_name }} {{ $t->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- What happened -->
                    <div>
                        <p class="text-[11px] font-bold uppercase text-indigo-600 mb-2">2 · What happened</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="md:col-span-3">
                                <label class="block text-xs font-bold uppercase mb-1">Incident (short title) *</label>
                                <input type="text" name="title" required placeholder="e.g. Skipped afternoon periods"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold uppercase mb-1">Category *</label>
                                <select name="category" required class="w-full text-xs border rounded-lg p-2.5">
                                    @foreach (\App\Models\DisciplineIncident::CATEGORIES as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Severity *</label>
                                <select name="severity" required class="w-full text-xs border rounded-lg p-2.5">
                                    @foreach (\App\Models\DisciplineIncident::SEVERITIES as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-xs font-bold uppercase mb-1">Description</label>
                                <textarea name="description" rows="2" placeholder="Facts only: what happened, when, who was involved."
                                    class="w-full text-xs border rounded-lg p-2.5"></textarea>
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-xs font-bold uppercase mb-1">Witnesses</label>
                                <input type="text" name="witnesses" placeholder="Teachers or students who saw it"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                        </div>
                    </div>

                    <!-- Action -->
                    <div>
                        <p class="text-[11px] font-bold uppercase text-indigo-600 mb-2">3 · Action taken</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Action type</label>
                                <select name="action_type" class="w-full text-xs border rounded-lg p-2.5">
                                    <option value="">— None yet —</option>
                                    @foreach (\App\Models\DisciplineIncident::ACTIONS as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold uppercase mb-1">Action details</label>
                                <input type="text" name="action_taken"
                                    placeholder="e.g. Seat changed, 3 days extra practice"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                        </div>
                    </div>

                    <!-- Parent -->
                    <div>
                        <p class="text-[11px] font-bold uppercase text-indigo-600 mb-2">4 · Parent communication</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                            <label class="flex items-center gap-2 text-xs font-bold md:pb-2.5">
                                <input type="checkbox" name="parent_notified" id="fNotified" value="1"
                                    class="rounded border-gray-300 text-emerald-600">
                                Parent has been notified
                            </label>
                            <div class="notify-field">
                                <label class="block text-xs font-bold uppercase mb-1">Notified on</label>
                                <input type="date" name="parent_notified_on" max="{{ $today }}"
                                    class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                            <div class="notify-field">
                                <label class="block text-xs font-bold uppercase mb-1">How</label>
                                <select name="notified_via" class="w-full text-xs border rounded-lg p-2.5">
                                    <option value="">—</option>
                                    @foreach (\App\Models\DisciplineIncident::NOTIFY_METHODS as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <p class="text-[11px] font-bold uppercase text-indigo-600 mb-2">5 · Follow-up and status</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Follow-up date</label>
                                <input type="date" name="follow_up_date" class="w-full text-xs border rounded-lg p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase mb-1">Status *</label>
                                <select name="status" id="fStatus" required
                                    class="w-full text-xs border rounded-lg p-2.5">
                                    @foreach ($statusLabels as $v => $l)
                                        <option value="{{ $v }}">{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-3" id="resolutionWrap" style="display:none;">
                                <label class="block text-xs font-bold uppercase mb-1">Resolution note * (required to
                                    close)</label>
                                <textarea name="resolution" rows="2"
                                    placeholder="How was this resolved? Agreement with parents, change in behaviour..."
                                    class="w-full text-xs border rounded-lg p-2.5"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-2 pt-2">
                        <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                            data-target="formModal">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Save
                            Incident</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Close case modal -->
        <div id="closeModal" style="display:none;"
            class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <h3 class="text-md font-bold">Close Case</h3>
                    <button type="button" class="close-modal text-xl" data-target="closeModal">&times;</button>
                </div>
                <form id="closeForm" method="POST" action="" class="space-y-4">
                    @csrf @method('PATCH')
                    <p class="text-xs text-gray-600" id="closeWho"></p>
                    <div>
                        <label class="block text-xs font-bold uppercase mb-1">Resolution note *</label>
                        <textarea name="resolution" rows="3" required placeholder="How was this resolved?"
                            class="w-full text-xs border rounded-lg p-2.5"></textarea>
                    </div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                            data-target="closeModal">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Close
                            Case</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- View modal -->
        <div id="viewModal" style="display:none;"
            class="no-print fixed inset-0 z-50 items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <h3 class="text-md font-bold">Incident Details</h3>
                    <button type="button" class="close-modal text-xl" data-target="viewModal">&times;</button>
                </div>

                <div id="vRepeat"
                    class="hidden mb-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs px-3 py-2 rounded-lg font-semibold">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Student</p>
                        <p id="v_student_name" class="text-gray-900 font-bold"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Class</p>
                        <p id="v_class_name" class="text-gray-900"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Date</p>
                        <p id="v_incident_date" class="text-gray-900"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Location</p>
                        <p id="v_location" class="text-gray-900"></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Incident</p>
                        <p id="v_title" class="text-gray-900 font-bold"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Category</p>
                        <p id="v_category_label" class="text-gray-900"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Severity</p>
                        <p id="v_severity_label" class="text-gray-900 font-bold"></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Description</p>
                        <p id="v_description" class="text-gray-900 whitespace-pre-line"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Witnesses</p>
                        <p id="v_witnesses" class="text-gray-900"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Reported by</p>
                        <p id="v_reporter_name" class="text-gray-900"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Action type</p>
                        <p id="v_action_label" class="text-gray-900"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Action details</p>
                        <p id="v_action_taken" class="text-gray-900"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Parent notified</p>
                        <p id="v_notified" class="text-gray-900"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Follow-up date</p>
                        <p id="v_follow_up_date" class="text-gray-900"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Status</p>
                        <p id="v_status_label" class="text-gray-900 font-bold"></p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Closed on</p>
                        <p id="v_closed_at" class="text-gray-900"></p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="font-bold uppercase text-gray-400 mb-0.5">Resolution</p>
                        <p id="v_resolution" class="text-gray-900 whitespace-pre-line"></p>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="button" class="close-modal px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg"
                        data-target="viewModal">Close</button>
                </div>
            </div>
        </div>

        <script>
            (function() {
                var STORE_URL = @json(route('admin.discipline.store'));
                var BASE_URL = @json(url('/admin/discipline'));
                var TODAY = @json($today);
                var STUDENTS = @json($studentsJs);

                var FIELDS = ['incident_date', 'student_id', 'reported_by', 'location', 'title', 'category', 'severity',
                    'description', 'witnesses', 'action_type', 'action_taken', 'parent_notified_on', 'notified_via',
                    'follow_up_date', 'status', 'resolution'
                ];

                var form = document.getElementById('incidentForm');
                var fClass = document.getElementById('fClass');
                var fStudent = document.getElementById('fStudent');
                var fNotified = document.getElementById('fNotified');
                var fStatus = document.getElementById('fStatus');

                function showModal(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = 'flex';
                }

                function hideModal(id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = 'none';
                }

                // Class -> student dropdown
                function fillStudents(classId, selectedId) {
                    fStudent.innerHTML = '';
                    var first = document.createElement('option');
                    first.value = '';
                    first.textContent = classId ? 'Select student...' : 'Select class first...';
                    fStudent.appendChild(first);

                    STUDENTS.filter(function(s) {
                            return String(s.class_id) === String(classId);
                        })
                        .forEach(function(s) {
                            var o = document.createElement('option');
                            o.value = s.id;
                            o.textContent = (s.roll ? s.roll + ' · ' : '') + s.name;
                            fStudent.appendChild(o);
                        });

                    if (selectedId) fStudent.value = selectedId;
                }

                function syncNotify() {
                    document.querySelectorAll('.notify-field').forEach(function(el) {
                        el.style.opacity = fNotified.checked ? '1' : '.4';
                    });
                    if (fNotified.checked && !form.elements['parent_notified_on'].value) form.elements['parent_notified_on']
                        .value = TODAY;
                }

                function syncResolution() {
                    var closed = fStatus.value === 'closed';
                    document.getElementById('resolutionWrap').style.display = closed ? 'block' : 'none';
                    form.elements['resolution'].required = closed;
                }

                function openForm(mode, data, id) {
                    data = data || {};
                    var edit = mode === 'edit';

                    document.getElementById('formTitle').textContent = edit ? 'Edit Incident' : 'Report Incident';
                    form.setAttribute('action', edit ? BASE_URL + '/' + id : STORE_URL);
                    document.getElementById('formMethod').disabled = !edit;
                    document.getElementById('formMode').value = mode;
                    document.getElementById('formEditId').value = id || '';

                    FIELDS.forEach(function(name) {
                        var el = form.elements[name];
                        if (el) el.value = (data[name] !== undefined && data[name] !== null) ? data[name] : '';
                    });

                    fClass.value = data.class_id || '';
                    fillStudents(fClass.value, data.student_id);

                    var n = data.parent_notified;
                    fNotified.checked = (n === true || n === 1 || n === '1' || n === 'on');

                    if (!form.elements['incident_date'].value) form.elements['incident_date'].value = TODAY;
                    if (!form.elements['status'].value) form.elements['status'].value = 'open';
                    if (!form.elements['severity'].value) form.elements['severity'].value = 'low';
                    if (!form.elements['category'].value) form.elements['category'].selectedIndex = 0;

                    syncNotify();
                    syncResolution();
                    showModal('formModal');
                }

                function openView(p) {
                    function set(id, v) {
                        document.getElementById(id).textContent = (v !== undefined && v !== null && v !== '') ? v : '—';
                    }

                    set('v_student_name', p.student_name);
                    set('v_class_name', p.class_name);
                    set('v_incident_date', p.incident_date);
                    set('v_location', p.location);
                    set('v_title', p.title);
                    set('v_category_label', p.category_label);
                    set('v_severity_label', p.severity_label);
                    set('v_description', p.description);
                    set('v_witnesses', p.witnesses);
                    set('v_reporter_name', p.reporter_name);
                    set('v_action_label', p.action_label);
                    set('v_action_taken', p.action_taken);
                    set('v_follow_up_date', p.follow_up_date);
                    set('v_status_label', p.status_label);
                    set('v_closed_at', p.closed_at);
                    set('v_resolution', p.resolution);

                    var notified = 'No';
                    if (p.parent_notified) {
                        notified = 'Yes' + (p.parent_notified_on ? ' · ' + p.parent_notified_on : '') + (p.notified_label ?
                            ' · ' + p.notified_label : '');
                    }
                    set('v_notified', notified);

                    var rep = document.getElementById('vRepeat');
                    if (p.history > 1) {
                        rep.textContent = '⚠️ This student has ' + p.history +
                            ' incidents on record. Consider a parent meeting or counselling.';
                        rep.classList.remove('hidden');
                    } else {
                        rep.classList.add('hidden');
                    }

                    showModal('viewModal');
                }

                function rowData(btn) {
                    try {
                        return JSON.parse(btn.closest('tr').dataset.incident);
                    } catch (e) {
                        console.error(e);
                        return null;
                    }
                }

                fClass.addEventListener('change', function() {
                    fillStudents(fClass.value, null);
                });
                fNotified.addEventListener('change', syncNotify);
                fStatus.addEventListener('change', syncResolution);

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

                    if (['formModal', 'viewModal', 'closeModal'].indexOf(e.target.id) !== -1) {
                        hideModal(e.target.id);
                        return;
                    }

                    var editBtn = e.target.closest('.edit-btn');
                    if (editBtn) {
                        var d = rowData(editBtn);
                        if (d) openForm('edit', d, d.id);
                        return;
                    }

                    var viewBtn = e.target.closest('.view-btn');
                    if (viewBtn) {
                        var v = rowData(viewBtn);
                        if (v) openView(v);
                        return;
                    }

                    var closeCaseBtn = e.target.closest('.close-btn');
                    if (closeCaseBtn) {
                        var c = rowData(closeCaseBtn);
                        if (!c) return;
                        document.getElementById('closeForm').setAttribute('action', BASE_URL + '/' + c.id +
                            '/close');
                        document.getElementById('closeWho').textContent = c.student_name + ' — ' + c.title;
                        document.getElementById('closeForm').elements['resolution'].value = c.resolution || '';
                        showModal('closeModal');
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
