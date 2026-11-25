@extends('layouts.admin')

@section('content')
    @use('App\Models\Complaint', 'C')

    @php
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
        $masked = $complaint->hidesFilerFrom(auth()->id());
    @endphp

    <div class="py-6 max-w-6xl mx-auto sm:px-6 lg:px-8">
        @if (session('success'))
            <div
                class="mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <a href="{{ route('admin.complaints.index') }}" class="text-xs font-bold text-blue-700 hover:underline">← Back to
            complaints</a>

        <div class="flex flex-wrap items-center gap-3 mt-2 mb-5">
            <h2 class="text-xl font-bold text-gray-900">{{ $complaint->subject }}</h2>
            <span
                class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $statusBadge[$complaint->status] ?? '' }}">{{ C::STATUS_LABELS[$complaint->status] }}</span>
            @if ($complaint->is_escalated)
                <span
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-purple-100 text-purple-700">Escalated</span>
            @endif
            @if ($complaint->isOverdue())
                <span
                    class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-red-100 text-red-700">Overdue</span>
            @endif
            <span class="font-mono text-xs text-gray-400">{{ $complaint->complaint_no }}</span>
        </div>

        {{-- Outcome, shown to everyone once the complaint is closed --}}
        @if (in_array($complaint->status, ['resolved', 'dismissed']) && $complaint->resolution_note)
            <div
                class="mb-5 rounded-xl border p-4 text-sm {{ $complaint->status === 'resolved' ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-gray-50 border-gray-300 text-gray-800' }}">
                <p class="text-[11px] font-bold uppercase mb-1">
                    {{ $complaint->status === 'resolved' ? 'Resolution' : 'Dismissed' }}
                    · {{ $complaint->resolved_at?->format('Y-m-d') }}
                </p>
                <p class="whitespace-pre-line">{{ $complaint->resolution_note }}</p>
                @if ($complaint->status === 'resolved' && !$isManager)
                    <p class="text-xs mt-2 opacity-80">Not satisfied with the outcome? Reply below and the complaint will be
                        reopened.</p>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            <!-- Conversation -->
            <div class="lg:col-span-2 space-y-4">

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex justify-between text-xs text-gray-500 mb-3">
                        <span><b class="text-gray-900">{{ $complaint->filerName() }}</b> filed this complaint</span>
                        <span>{{ $complaint->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <p class="text-sm text-gray-800 whitespace-pre-line">{{ $complaint->description }}</p>
                    @if ($complaint->attachment_path)
                        <a href="{{ asset('storage/' . $complaint->attachment_path) }}" target="_blank"
                            class="inline-block mt-3 text-xs font-bold text-blue-700 hover:underline"><i class="bi bi-paperclip"></i> View evidence</a>
                    @endif
                </div>

                @foreach ($updates as $u)
                    @if ($u->type === 'system')
                        <p class="text-center text-[11px] {{ $u->is_internal ? 'text-amber-700' : 'text-gray-400' }}">
                            @if ($u->is_internal)
                                <i class="bi bi-lock"></i>
                            @endif
                            {{ $u->body }} · {{ $authorName($u->user) }} · {{ $u->created_at->diffForHumans() }}
                        </p>
                    @elseif ($u->type === 'resolution')
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                            <p class="text-[11px] font-bold uppercase text-emerald-800 mb-1">
                                Outcome note · {{ $authorName($u->user) }} · {{ $u->created_at->format('Y-m-d H:i') }}</p>
                            <p class="text-sm text-emerald-900 whitespace-pre-line">{{ $u->body }}</p>
                        </div>
                    @else
                        @php
                            $mine = $u->user_id === auth()->id();
                            $hideRole = $complaint->is_anonymous && $u->user_id === $complaint->filed_by && $masked;
                        @endphp
                        <div
                            class="rounded-xl border p-5 shadow-sm {{ $u->is_internal ? 'bg-amber-50 border-amber-300' : ($mine ? 'bg-indigo-50 border-indigo-100' : 'bg-white border-gray-100') }}">
                            <div class="flex justify-between text-xs text-gray-500 mb-2">
                                <span>
                                    <b class="text-gray-900">{{ $authorName($u->user) }}</b>
                                    @unless ($hideRole)
                                        <span class="text-gray-400">{{ $roles[$u->user_id] ?? '' }}</span>
                                    @endunless
                                    @if ($u->is_internal)
                                        <span
                                            class="ml-1 px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 text-[10px] font-bold uppercase">Internal
                                            note</span>
                                    @endif
                                </span>
                                <span
                                    title="{{ $u->created_at->format('Y-m-d H:i') }}">{{ $u->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-gray-800 whitespace-pre-line">{{ $u->body }}</p>
                            @if ($u->attachment_path)
                                <a href="{{ asset('storage/' . $u->attachment_path) }}" target="_blank"
                                    class="inline-block mt-2 text-xs font-bold text-blue-700 hover:underline"><i class="bi bi-paperclip"></i> View
                                    attachment</a>
                            @endif
                        </div>
                    @endif
                @endforeach

                @if ($complaint->status !== 'dismissed')
                    <form method="POST" action="{{ route('admin.complaints.reply', $complaint) }}"
                        enctype="multipart/form-data"
                        class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-3">
                        @csrf
                        <label class="block text-xs font-bold uppercase">
                            {{ $isManager ? 'Reply or add a note' : 'Add more information' }}</label>
                        <textarea name="body" rows="4" required maxlength="5000" class="w-full text-sm border rounded-lg p-2.5"></textarea>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-4">
                                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                                    class="text-xs">
                                @if ($isManager)
                                    <label class="flex items-center gap-1 text-xs font-semibold text-amber-800">
                                        <input type="checkbox" name="is_internal" value="1"> Internal note (staff only)
                                    </label>
                                @endif
                            </div>
                            <button type="submit"
                                class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-lg">Send</button>
                        </div>
                    </form>
                @else
                    <p class="text-xs text-gray-500 text-center">This complaint was dismissed. File a new complaint if you
                        have new information.</p>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-xs space-y-3">
                    <div>
                        <p class="font-bold uppercase text-gray-400">About</p>
                        <p class="text-gray-900">{{ C::AGAINST[$complaint->against] ?? $complaint->against }}
                            @if ($complaint->against_detail)
                                · {{ $complaint->against_detail }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400">Severity</p>
                        <span
                            class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $severityBadge[$complaint->severity] ?? '' }}">{{ $complaint->severity }}</span>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400">Filed by</p>
                        <p class="text-gray-900">{{ $complaint->filerName() }}
                            @unless ($masked)
                                <span class="text-gray-400">{{ $roles[$complaint->filed_by] ?? '' }}@if ($complaint->is_anonymous)
                                        · anonymous to others
                                    @endif
                                </span>
                            @endunless
                        </p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400">Handled by</p>
                        <p class="text-gray-900">
                            {{ $complaint->assignee ? $complaint->assignee->name ?? ($complaint->assignee->username ?? '—') : 'Not assigned yet' }}
                        </p>
                    </div>
                    @if ($complaint->incident_date)
                        <div>
                            <p class="font-bold uppercase text-gray-400">Happened on</p>
                            <p class="text-gray-900">{{ $complaint->incident_date->format('Y-m-d') }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="font-bold uppercase text-gray-400">Filed on</p>
                        <p class="text-gray-900">{{ $complaint->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @if ($complaint->escalated_at)
                        <div>
                            <p class="font-bold uppercase text-gray-400">Escalated</p>
                            <p class="text-gray-900">{{ $complaint->escalated_at->format('Y-m-d H:i') }}</p>
                        </div>
                    @endif
                </div>

                @if ($isManager)
                    <form method="POST" action="{{ route('admin.complaints.update', $complaint) }}"
                        class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-3">
                        @csrf @method('PATCH')
                        <p class="text-[11px] font-bold uppercase text-indigo-600">Manage complaint</p>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Status</label>
                            <select name="status" id="statusSel" class="w-full text-xs border rounded-lg p-2.5">
                                @foreach (C::STATUS_LABELS as $v => $l)
                                    <option value="{{ $v }}" @selected(old('status', $complaint->status) === $v)>{{ $l }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Severity</label>
                            <select name="severity" class="w-full text-xs border rounded-lg p-2.5">
                                @foreach (C::SEVERITIES as $v => $l)
                                    <option value="{{ $v }}" @selected($complaint->severity === $v)>{{ $l }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Assign to</label>
                            <select name="assigned_to" class="w-full text-xs border rounded-lg p-2.5">
                                <option value="">Unassigned</option>
                                @foreach ($staff as $s)
                                    <option value="{{ $s->id }}" @selected((int) $complaint->assigned_to === $s->id)>
                                        {{ $s->name ?? ($s->username ?? 'User #' . $s->id) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="resolutionBox">
                            <label class="block text-xs font-bold uppercase mb-1">Resolution note</label>
                            <textarea name="resolution_note" rows="3" maxlength="2000"
                                placeholder="What was found and what was done? Required to resolve or dismiss; the complainant will read it."
                                class="w-full text-xs border rounded-lg p-2.5">{{ old('resolution_note', $complaint->resolution_note) }}</textarea>
                        </div>
                        <button type="submit"
                            class="w-full px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg">Save
                            changes</button>
                    </form>

                    @if ($complaint->isActive() && !$complaint->is_escalated)
                        <form method="POST" action="{{ route('admin.complaints.escalate', $complaint) }}"
                            class="bg-purple-50 rounded-xl border border-purple-200 p-5 space-y-2"
                            onsubmit="return confirm('Escalate this complaint for senior review?');">
                            @csrf
                            <p class="text-[11px] font-bold uppercase text-purple-700">Escalate</p>
                            <input type="text" name="reason" required maxlength="255"
                                placeholder="Why does this need senior review?"
                                class="w-full text-xs border rounded-lg p-2.5">
                            <button type="submit"
                                class="w-full px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold rounded-lg">Escalate</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <script>
        // The resolution note only matters when resolving or dismissing, so dim it otherwise.
        (function() {
            var sel = document.getElementById('statusSel');
            var box = document.getElementById('resolutionBox');
            if (!sel || !box) return;
            var sync = function() {
                var closing = sel.value === 'resolved' || sel.value === 'dismissed';
                box.style.opacity = closing ? '1' : '0.5';
                var ta = box.querySelector('textarea');
                if (ta) ta.required = closing && !ta.value.trim();
            };
            sel.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
