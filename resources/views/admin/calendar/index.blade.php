@extends('layouts.admin')

@section('title', 'School Calendar')

@section('content')
    <div class="ui-page" x-data="{
        showAddModal: false,
        showEditModal: false,
        editData: {
            id: null,
            title: '',
            date: '',
            type: 'event',
            description: '',
            isPublic: false
        },
        openEdit(event) {
            this.editData = {
                id: event.id,
                title: event.title,
                date: event.date,
                type: event.type,
                description: event.description ?? '',
                isPublic: !!event.is_public
            };
            this.showEditModal = true;
        }
    }">
        <x-ui.page-header title="School Calendar"
            subtitle="Manage school holidays, internal events, and examination schedules.">
            <x-slot name="actions">
                <button type="button" onclick="window.print()" class="ui-btn ui-btn-secondary">
                    <i class="bi bi-printer"></i> Print
                </button>
                @can('calendar.manage')
                    <button type="button" @click="showAddModal = true" class="ui-btn ui-btn-primary">
                        <i class="bi bi-plus-lg"></i> Add event
                    </button>
                @endcan
            </x-slot>
        </x-ui.page-header>

        <div class="ui-tabs">
            <a href="{{ route('admin.calendar.index', ['tab' => 'all']) }}"
                @class(['ui-tab', 'ui-tab-active' => $tab === 'all'])>
                <i class="bi bi-calendar3"></i> All
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $totalCount }}</span>
            </a>
            <a href="{{ route('admin.calendar.index', ['tab' => 'holidays']) }}"
                @class(['ui-tab', 'ui-tab-active' => $tab === 'holidays'])>
                <i class="bi bi-sun"></i> Holidays
                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">{{ $holidayCount }}</span>
            </a>
            <a href="{{ route('admin.calendar.index', ['tab' => 'events']) }}"
                @class(['ui-tab', 'ui-tab-active' => $tab === 'events'])>
                <i class="bi bi-stars"></i> Events
                <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-700">{{ $eventCount }}</span>
            </a>
            <a href="{{ route('admin.calendar.index', ['tab' => 'exams']) }}"
                @class(['ui-tab', 'ui-tab-active' => $tab === 'exams'])>
                <i class="bi bi-journal-check"></i> Exams
                <span class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-700">{{ $examCount }}</span>
            </a>
        </div>

        <section class="ui-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="w-32">Date</th>
                            <th>Event</th>
                            <th class="w-32">Type</th>
                            <th class="w-28">Public</th>
                            <th>Description</th>
                            <th class="w-24 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($events as $event)
                            <tr>
                                <td class="whitespace-nowrap font-mono text-xs font-semibold text-slate-600">
                                    {{ $event->date }}
                                </td>
                                <td class="font-semibold text-slate-900">{{ $event->title }}</td>
                                <td>
                                    @php
                                        $tone = match ($event->type) {
                                            'holiday' => 'danger',
                                            'exam' => 'warning',
                                            default => 'info',
                                        };
                                    @endphp
                                    <x-ui.badge :tone="$tone">{{ $event->type }}</x-ui.badge>
                                </td>
                                <td>
                                    <x-ui.badge :tone="$event->is_public ? 'success' : 'neutral'">
                                        {{ $event->is_public ? 'Public' : 'Unpublished' }}
                                    </x-ui.badge>
                                </td>
                                <td class="max-w-md text-slate-600">{{ $event->description ?? '—' }}</td>
                                <td class="text-right">
                                    @can('calendar.manage')
                                        @if (! $event->is_public || auth()->user()?->can('public_calendar.manage'))
                                            <div class="inline-flex items-center gap-1">
                                                @can('public_calendar.manage')
                                                    <a href="{{ route('admin.public-localisations.content.edit', ['school_event', $event]) }}"
                                                        aria-label="Manage Nepali translation for {{ $event->title }}"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-sky-50 hover:text-sky-600">
                                                        <i class="bi bi-translate"></i>
                                                    </a>
                                                @endcan
                                                <button type="button" @click="openEdit({{ json_encode($event) }})"
                                                    aria-label="Edit {{ $event->title }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-amber-50 hover:text-amber-600">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form action="{{ route('admin.calendar.destroy', $event->id) }}" method="POST"
                                                    class="inline"
                                                    onsubmit="return confirm('Delete this calendar event?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" aria-label="Delete {{ $event->title }}"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10">
                                    <x-ui.empty-state icon="bi-calendar-x" title="No calendar events found"
                                        message="Holidays, school events, and exam schedules will appear here." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @can('calendar.manage')
            <div x-show="showAddModal" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
                role="dialog" aria-modal="true" aria-labelledby="addEventTitle">
                <div @click.outside="showAddModal = false"
                    class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h3 id="addEventTitle" class="flex items-center gap-2 text-base font-bold text-slate-900">
                            <i class="bi bi-calendar-plus text-emerald-600"></i> Add calendar event
                        </h3>
                        <button type="button" @click="showAddModal = false" aria-label="Close"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                            <i class="bi bi-x-lg text-sm"></i>
                        </button>
                    </div>

                    <form action="{{ route('admin.calendar.store') }}" method="POST">
                        @csrf
                        <div class="space-y-4 p-6">
                            <div>
                                <label for="add_title" class="ui-label">Event title <span class="text-rose-600">*</span></label>
                                <input type="text" name="title" id="add_title" value="{{ old('title') }}" required class="ui-input">
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="add_date" class="ui-label">Date <span class="text-rose-600">*</span></label>
                                    <input type="date" name="date" id="add_date"
                                        value="{{ old('date', date('Y-m-d')) }}" required class="ui-input">
                                </div>
                                <div>
                                    <label for="add_type" class="ui-label">Type <span class="text-rose-600">*</span></label>
                                    <select name="type" id="add_type" required class="ui-input">
                                        <option value="holiday" {{ old('type') === 'holiday' ? 'selected' : '' }}>Holiday</option>
                                        <option value="event" {{ old('type', 'event') === 'event' ? 'selected' : '' }}>Event</option>
                                        <option value="exam" {{ old('type') === 'exam' ? 'selected' : '' }}>Exam</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="add_description" class="ui-label">Description</label>
                                <textarea name="description" id="add_description" rows="3" class="ui-input">{{ old('description') }}</textarea>
                            </div>

                            <div>
                                <label class="ui-label">Publication</label>
                                @can('public_calendar.manage')
                                    <label class="inline-flex select-none items-center gap-2 py-1 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" name="is_public" id="add_is_public" value="1"
                                            @checked((bool) old('is_public'))
                                            class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500 shrink-0">
                                        Show on public website
                                    </label>
                                @else
                                    <p class="text-sm leading-6 text-slate-600">This event will remain private to staff. An administrator manages public calendar content.</p>
                                @endcan
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                            <button type="button" @click="showAddModal = false" class="ui-btn ui-btn-secondary">Cancel</button>
                            <button type="submit" class="ui-btn ui-btn-primary">
                                <i class="bi bi-check2"></i> Save event
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="showEditModal" x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
                role="dialog" aria-modal="true" aria-labelledby="editEventTitle">
                <div @click.outside="showEditModal = false"
                    class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                        <h3 id="editEventTitle" class="flex items-center gap-2 text-base font-bold text-slate-900">
                            <i class="bi bi-pencil-square text-emerald-600"></i> Edit calendar event
                        </h3>
                        <button type="button" @click="showEditModal = false" aria-label="Close"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                            <i class="bi bi-x-lg text-sm"></i>
                        </button>
                    </div>

                    <form :action="'{{ url('admin/calendar') }}/' + editData.id" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="space-y-4 p-6">
                            <div>
                                <label for="edit_title" class="ui-label">Event title <span class="text-rose-600">*</span></label>
                                <input type="text" name="title" id="edit_title" x-model="editData.title" required class="ui-input">
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="edit_date" class="ui-label">Date <span class="text-rose-600">*</span></label>
                                    <input type="date" name="date" id="edit_date" x-model="editData.date" required class="ui-input">
                                </div>
                                <div>
                                    <label for="edit_type" class="ui-label">Type <span class="text-rose-600">*</span></label>
                                    <select name="type" id="edit_type" x-model="editData.type" required class="ui-input">
                                        <option value="holiday">Holiday</option>
                                        <option value="event">Event</option>
                                        <option value="exam">Exam</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="edit_description" class="ui-label">Description</label>
                                <textarea name="description" id="edit_description" rows="3" x-model="editData.description"
                                    class="ui-input"></textarea>
                            </div>

                            <div>
                                <label class="ui-label">Publication</label>
                                @can('public_calendar.manage')
                                    <label class="inline-flex select-none items-center gap-2 py-1 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" name="is_public" id="edit_is_public" value="1"
                                            x-model.boolean="editData.isPublic"
                                            class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500 shrink-0">
                                        Show on public website
                                    </label>
                                @else
                                    <p class="text-sm leading-6 text-slate-600">This event remains private to staff. An administrator manages public calendar content.</p>
                                @endcan
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                            <button type="button" @click="showEditModal = false" class="ui-btn ui-btn-secondary">Cancel</button>
                            <button type="submit" class="ui-btn ui-btn-primary">
                                <i class="bi bi-check2"></i> Update event
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection
