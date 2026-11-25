@extends('layouts.admin')

@section('content')
    @use('App\Models\HelpdeskTicket', 'T')

    @php
        $statusBadge = [
            'open' => 'bg-rose-100 text-rose-800',
            'in_progress' => 'bg-amber-100 text-amber-800',
            'resolved' => 'bg-emerald-100 text-emerald-800',
            'closed' => 'bg-gray-200 text-gray-700',
        ];
        $priorityBadge = [
            'high' => 'bg-rose-100 text-rose-800',
            'medium' => 'bg-amber-100 text-amber-800',
            'low' => 'bg-sky-100 text-sky-800',
        ];
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

        <a href="{{ route('admin.helpdesk.index') }}" class="text-xs font-bold text-blue-700 hover:underline">← Back to
            tickets</a>

        <div class="flex flex-wrap items-center gap-3 mt-2 mb-5">
            <h2 class="text-xl font-bold text-gray-900">{{ $ticket->subject }}</h2>
            <span
                class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $statusBadge[$ticket->status] ?? '' }}">{{ T::STATUS_LABELS[$ticket->status] }}</span>
            @if ($ticket->isOverdue())
                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-red-100 text-red-700">Overdue</span>
            @endif
            <span class="font-mono text-xs text-gray-400">{{ $ticket->ticket_no }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            <!-- Conversation -->
            <div class="lg:col-span-2 space-y-4">

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex justify-between text-xs text-gray-500 mb-3">
                        <span><b class="text-gray-900">{{ $names($ticket->raiser) }}</b> raised this ticket</span>
                        <span>{{ $ticket->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                    <p class="text-sm text-gray-800 whitespace-pre-line">{{ $ticket->description }}</p>
                    @if ($ticket->attachment_path)
                        <a href="{{ asset('storage/' . $ticket->attachment_path) }}" target="_blank"
                            class="inline-block mt-3 text-xs font-bold text-blue-700 hover:underline"><i class="bi bi-paperclip"></i> View attachment</a>
                    @endif
                </div>

                @foreach ($replies as $r)
                    @if ($r->type === 'system')
                        <p class="text-center text-[11px] text-gray-400">
                            {{ $r->body }} · {{ $names($r->user) }} · {{ $r->created_at->diffForHumans() }}</p>
                    @else
                        @php $mine = $r->user_id === auth()->id(); @endphp
                        <div
                            class="rounded-xl border p-5 shadow-sm {{ $r->is_internal ? 'bg-amber-50 border-amber-300' : ($mine ? 'bg-indigo-50 border-indigo-100' : 'bg-white border-gray-100') }}">
                            <div class="flex justify-between text-xs text-gray-500 mb-2">
                                <span>
                                    <b class="text-gray-900">{{ $names($r->user) }}</b>
                                    <span class="text-gray-400">{{ $r->user->role ?? '' }}</span>
                                    @if ($r->is_internal)
                                        <span
                                            class="ml-1 px-1.5 py-0.5 rounded bg-amber-200 text-amber-900 text-[10px] font-bold uppercase">Internal
                                            note</span>
                                    @endif
                                </span>
                                <span
                                    title="{{ $r->created_at->format('Y-m-d H:i') }}">{{ $r->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-sm text-gray-800 whitespace-pre-line">{{ $r->body }}</p>
                            @if ($r->attachment_path)
                                <a href="{{ asset('storage/' . $r->attachment_path) }}" target="_blank"
                                    class="inline-block mt-2 text-xs font-bold text-blue-700 hover:underline"><i class="bi bi-paperclip"></i> View
                                    attachment</a>
                            @endif
                        </div>
                    @endif
                @endforeach

                @if ($ticket->status !== 'closed')
                    <form method="POST" action="{{ route('admin.helpdesk.reply', $ticket) }}"
                        enctype="multipart/form-data"
                        class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-3">
                        @csrf
                        <label class="block text-xs font-bold uppercase">Your reply</label>
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
                    <p class="text-xs text-gray-500 text-center">This ticket is closed.</p>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-xs space-y-3">
                    <div>
                        <p class="font-bold uppercase text-gray-400">Category</p>
                        <p class="text-gray-900">{{ T::CATEGORIES[$ticket->category] ?? $ticket->category }}</p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400">Priority</p>
                        <span
                            class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $priorityBadge[$ticket->priority] ?? '' }}">{{ $ticket->priority }}</span>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400">Assigned to</p>
                        <p class="text-gray-900">{{ $names($ticket->assignee) }}</p>
                    </div>
                    <div>
                        <p class="font-bold uppercase text-gray-400">Created</p>
                        <p class="text-gray-900">{{ $ticket->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @if ($ticket->resolved_at)
                        <div>
                            <p class="font-bold uppercase text-gray-400">Resolved</p>
                            <p class="text-gray-900">{{ $ticket->resolved_at->format('Y-m-d H:i') }}</p>
                        </div>
                    @endif
                </div>

                @if ($isManager)
                    <form method="POST" action="{{ route('admin.helpdesk.update', $ticket) }}"
                        class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-3">
                        @csrf @method('PATCH')
                        <p class="text-[11px] font-bold uppercase text-indigo-600">Manage ticket</p>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Status</label>
                            <select name="status" class="w-full text-xs border rounded-lg p-2.5">
                                @foreach (T::STATUS_LABELS as $v => $l)
                                    <option value="{{ $v }}" @selected($ticket->status === $v)>{{ $l }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Priority</label>
                            <select name="priority" class="w-full text-xs border rounded-lg p-2.5">
                                @foreach (T::PRIORITIES as $v => $l)
                                    <option value="{{ $v }}" @selected($ticket->priority === $v)>{{ $l }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase mb-1">Assign to</label>
                            <select name="assigned_to" class="w-full text-xs border rounded-lg p-2.5">
                                <option value="">Unassigned</option>
                                @foreach ($staff as $s)
                                    <option value="{{ $s->id }}" @selected((int) $ticket->assigned_to === $s->id)>{{ $names($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                            class="w-full px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg">Save
                            changes</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
