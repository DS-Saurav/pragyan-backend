@extends('layouts.admin')

@section('title', 'Substitute Management')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Substitute Management"
            subtitle="When a teacher is absent, load their timetable periods and allocate another teacher to cover them.">
            <x-slot name="actions">
                <button type="button" onclick="openAllocateModal()" class="ui-btn ui-btn-primary">
                    <i class="bi bi-plus-lg"></i> Allocate substitute
                </button>
            </x-slot>
        </x-ui.page-header>

        <section class="ui-card overflow-hidden">
            <div class="ui-card-header">
                <form method="GET" action="{{ route('admin.substitutes.index') }}" class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <label for="per_page" class="font-semibold">Show</label>
                        <select id="per_page" name="per_page" onchange="this.form.submit()"
                            class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700">
                            <option value="10" {{ (int) request('per_page', 10) === 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ (int) request('per_page') === 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ (int) request('per_page') === 50 ? 'selected' : '' }}>50</option>
                        </select>
                        <span>entries</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search teacher or period..."
                            class="ui-input w-56 py-1.5">
                        <button type="submit" class="ui-btn ui-btn-secondary ui-btn-sm">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </form>

                <span class="text-xs font-semibold text-slate-500">
                    {{ $entries->total() }} {{ \Illuminate\Support\Str::plural('allocation', $entries->total()) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Absent teacher</th>
                            <th>Substitute</th>
                            <th>Periods covered</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <p class="font-semibold text-slate-900">{{ $entry->allocation_date->format('M j, Y') }}</p>
                                    <p class="text-xs text-slate-500">{{ $entry->day_of_week }}</p>
                                </td>
                                <td class="font-medium text-slate-900">{{ $entry->absentTeacher->name ?? 'N/A' }}</td>
                                <td class="font-medium text-emerald-700">{{ $entry->substituteTeacher->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700">
                                        <i class="bi bi-clock"></i> {{ $entry->periods_covered }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $tone = match ($entry->status) {
                                            'completed' => 'success',
                                            'cancelled' => 'danger',
                                            default => 'info',
                                        };
                                    @endphp
                                    <x-ui.badge :tone="$tone">{{ $entry->status }}</x-ui.badge>
                                </td>
                                <td class="text-right">
                                    <form action="{{ route('admin.substitutes.destroy', $entry->id) }}" method="POST"
                                        class="inline" onsubmit="return confirm('Remove this substitute allocation?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" aria-label="Remove allocation"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10">
                                    <x-ui.empty-state icon="bi-arrow-repeat" title="No substitute allocations"
                                        message="Allocate a substitute when a teacher is absent to keep classes covered." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($entries->hasPages())
                <div class="ui-card-footer">
                    {{ $entries->appends(request()->query())->links() }}
                </div>
            @endif
        </section>
    </div>

    <div id="allocateModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        role="dialog" aria-modal="true" aria-labelledby="allocateModalTitle">
        <div class="w-full max-w-xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <h3 id="allocateModalTitle" class="flex items-center gap-2 text-base font-bold text-slate-900">
                    <i class="bi bi-arrow-repeat text-emerald-600"></i> Allocate substitute
                </h3>
                <button type="button" onclick="closeAllocateModal()" aria-label="Close"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <form action="{{ route('admin.substitutes.store') }}" method="POST">
                @csrf
                <div class="space-y-4 p-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="allocation_date" class="ui-label">Date <span class="text-rose-600">*</span></label>
                            <input type="date" name="allocation_date" id="allocation_date" value="{{ date('Y-m-d') }}"
                                required class="ui-input">
                        </div>
                        <div>
                            <label for="day_of_week" class="ui-label">Day (for timetable lookup)</label>
                            <select name="day_of_week" id="day_of_week" required class="ui-input">
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="absent_teacher_id" class="ui-label">Absent teacher <span class="text-rose-600">*</span></label>
                            <select name="absent_teacher_id" id="absent_teacher_id" required class="ui-input">
                                <option value="">Select teacher...</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="substitute_teacher_id" class="ui-label">Substitute teacher <span class="text-rose-600">*</span></label>
                            <select name="substitute_teacher_id" id="substitute_teacher_id" required class="ui-input">
                                <option value="">Select teacher...</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="periods_covered" class="ui-label">Periods to cover <span class="text-rose-600">*</span></label>
                        <input type="text" name="periods_covered" id="periods_covered"
                            placeholder="e.g. Period 1 - Mathematics (08:30 - 09:15)" required class="ui-input">
                    </div>

                    <div>
                        <label for="notes" class="ui-label">Notes</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="Optional instructions or room changes..."
                            class="ui-input"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                    <button type="button" onclick="closeAllocateModal()" class="ui-btn ui-btn-secondary">Cancel</button>
                    <button type="submit" class="ui-btn ui-btn-primary">
                        <i class="bi bi-check2"></i> Allocate
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            var allocateModal = document.getElementById('allocateModal');

            function openAllocateModal() {
                allocateModal.classList.remove('hidden');
                allocateModal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                document.getElementById('allocation_date').focus();
            }

            function closeAllocateModal() {
                allocateModal.classList.add('hidden');
                allocateModal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }

            allocateModal.addEventListener('click', function (event) {
                if (event.target === allocateModal) {
                    closeAllocateModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeAllocateModal();
                }
            });

            document.getElementById('allocation_date').addEventListener('change', function () {
                const dateObj = new Date(this.value);
                if (!isNaN(dateObj)) {
                    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    document.getElementById('day_of_week').value = days[dateObj.getDay()];
                }
            });
        </script>
    @endpush
@endsection
