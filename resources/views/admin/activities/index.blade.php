@extends('layouts.admin')

@section('title', 'Activities')

@section('content')
    <div class="ui-page">
        <x-ui.page-header title="Activities"
            subtitle="Plan and track school events, competitions, and co-curricular programmes.">
            <x-slot name="actions">
                <button type="button" onclick="openAddModal()" class="ui-btn ui-btn-primary">
                    <i class="bi bi-plus-lg"></i> Add activity
                </button>
            </x-slot>
        </x-ui.page-header>

        <div class="ui-tabs">
            <a href="{{ route('admin.activities.index') }}"
                @class(['ui-tab', 'ui-tab-active' => ! request('status')])>
                <i class="bi bi-grid"></i> All
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $totalCount }}</span>
            </a>
            <a href="{{ route('admin.activities.index', ['status' => 'scheduled']) }}"
                @class(['ui-tab', 'ui-tab-active' => request('status') === 'scheduled'])>
                <i class="bi bi-clock"></i> Scheduled
                <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-700">{{ $scheduledCount }}</span>
            </a>
            <a href="{{ route('admin.activities.index', ['status' => 'completed']) }}"
                @class(['ui-tab', 'ui-tab-active' => request('status') === 'completed'])>
                <i class="bi bi-check2-circle"></i> Completed
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">{{ $completedCount }}</span>
            </a>
        </div>

        <section class="ui-card overflow-hidden">
            <div class="ui-card-header">
                <form method="GET" action="{{ route('admin.activities.index') }}"
                    class="flex flex-wrap items-center gap-2">
                    @if (request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif

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
                        <input type="search" name="search" value="{{ request('search') }}"
                            placeholder="Search title or type..." class="ui-input w-56 py-1.5">
                        <button type="submit" class="ui-btn ui-btn-secondary ui-btn-sm">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </form>

                <span class="text-xs font-semibold text-slate-500">
                    {{ $entries->total() }} {{ \Illuminate\Support\Str::plural('activity', $entries->total()) }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Activity</th>
                            <th>Type</th>
                            <th>In-charge</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <p class="font-semibold text-slate-900">{{ $entry->activity_date->format('M j, Y') }}</p>
                                    <p class="text-xs text-slate-500">{{ $entry->activity_date->format('l') }}</p>
                                </td>
                                <td class="font-medium text-slate-900">{{ $entry->title }}</td>
                                <td>
                                    <x-ui.badge tone="neutral">{{ $entry->type }}</x-ui.badge>
                                </td>
                                <td>{{ $entry->inCharge->name ?? 'N/A' }}</td>
                                <td>
                                    <x-ui.badge :tone="$entry->status === 'completed' ? 'success' : 'info'">
                                        {{ $entry->status }}
                                    </x-ui.badge>
                                </td>
                                <td class="text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button" onclick="openEditModal({{ $entry->id }})"
                                            aria-label="Edit {{ $entry->title }}"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-amber-50 hover:text-amber-600">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('admin.activities.destroy', $entry->id) }}" method="POST"
                                            class="inline"
                                            onsubmit="return confirm('Are you sure you want to delete this activity?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="Delete {{ $entry->title }}"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10">
                                    <x-ui.empty-state icon="bi-trophy" title="No activities found"
                                        message="Add an activity to start planning events and co-curricular programmes.">
                                        <x-slot name="actions">
                                            <button type="button" onclick="openAddModal()" class="ui-btn ui-btn-primary ui-btn-sm">
                                                <i class="bi bi-plus-lg"></i> Add activity
                                            </button>
                                        </x-slot>
                                    </x-ui.empty-state>
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

    <div id="addModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <h3 id="addModalTitle" class="flex items-center gap-2 text-base font-bold text-slate-900">
                    <i class="bi bi-trophy text-emerald-600"></i> Add activity
                </h3>
                <button type="button" onclick="closeAddModal()" aria-label="Close"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <form action="{{ route('admin.activities.store') }}" method="POST">
                @csrf
                <div class="space-y-4 p-6">
                    <div>
                        <label for="activity_date" class="ui-label">Date <span class="text-rose-600">*</span></label>
                        <input type="date" name="activity_date" id="activity_date"
                            value="{{ old('activity_date', date('Y-m-d')) }}" required class="ui-input">
                    </div>

                    <div>
                        <label for="title" class="ui-label">Activity title <span class="text-rose-600">*</span></label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}"
                            placeholder="e.g. Annual Science Fair" required class="ui-input">
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="type" class="ui-label">Type <span class="text-rose-600">*</span></label>
                            <input type="text" name="type" id="type" value="{{ old('type') }}"
                                placeholder="e.g. Cultural, Sports" required class="ui-input">
                        </div>
                        <div>
                            <label for="status" class="ui-label">Status <span class="text-rose-600">*</span></label>
                            <select name="status" id="status" required class="ui-input">
                                <option value="scheduled" {{ old('status') === 'completed' ? '' : 'selected' }}>Scheduled</option>
                                <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="in_charge_id" class="ui-label">In-charge teacher <span class="text-rose-600">*</span></label>
                        <select name="in_charge_id" id="in_charge_id" required class="ui-input">
                            <option value="">Select teacher...</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" {{ (string) old('in_charge_id') === (string) $teacher->id ? 'selected' : '' }}>
                                    {{ $teacher->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                    <button type="button" onclick="closeAddModal()" class="ui-btn ui-btn-secondary">Cancel</button>
                    <button type="submit" class="ui-btn ui-btn-primary">
                        <i class="bi bi-check2"></i> Save activity
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <h3 id="editModalTitle" class="flex items-center gap-2 text-base font-bold text-slate-900">
                    <i class="bi bi-pencil-square text-emerald-600"></i> Edit activity
                </h3>
                <button type="button" onclick="closeEditModal()" aria-label="Close"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <form id="editForm" method="POST">
                @csrf
                @method('PUT')
                <div class="space-y-4 p-6">
                    <div>
                        <label for="edit_activity_date" class="ui-label">Date <span class="text-rose-600">*</span></label>
                        <input type="date" id="edit_activity_date" name="activity_date" required class="ui-input">
                    </div>

                    <div>
                        <label for="edit_title" class="ui-label">Activity title <span class="text-rose-600">*</span></label>
                        <input type="text" id="edit_title" name="title" required class="ui-input">
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="edit_type" class="ui-label">Type <span class="text-rose-600">*</span></label>
                            <input type="text" id="edit_type" name="type" required class="ui-input">
                        </div>
                        <div>
                            <label for="edit_status" class="ui-label">Status <span class="text-rose-600">*</span></label>
                            <select id="edit_status" name="status" required class="ui-input">
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="edit_in_charge_id" class="ui-label">In-charge teacher <span class="text-rose-600">*</span></label>
                        <select id="edit_in_charge_id" name="in_charge_id" required class="ui-input">
                            <option value="">Select teacher...</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                    <button type="button" onclick="closeEditModal()" class="ui-btn ui-btn-secondary">Cancel</button>
                    <button type="submit" class="ui-btn ui-btn-primary">
                        <i class="bi bi-check2"></i> Update activity
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            var addModal = document.getElementById('addModal');
            var editModal = document.getElementById('editModal');

            function openAddModal() {
                addModal.classList.remove('hidden');
                addModal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                document.getElementById('activity_date').focus();
            }

            function closeAddModal() {
                addModal.classList.add('hidden');
                addModal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }

            function openEditModal(id) {
                fetch(`{{ url('/admin/activities') }}/${id}/edit`)
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Unable to load activity.');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        document.getElementById('editForm').action = `{{ url('/admin/activities') }}/${id}`;
                        document.getElementById('edit_activity_date').value = data.entry.activity_date.split('T')[0];
                        document.getElementById('edit_title').value = data.entry.title;
                        document.getElementById('edit_type').value = data.entry.type;
                        document.getElementById('edit_status').value = data.entry.status;
                        document.getElementById('edit_in_charge_id').value = data.entry.in_charge_id;
                        editModal.classList.remove('hidden');
                        editModal.classList.add('flex');
                        document.body.classList.add('overflow-hidden');
                        document.getElementById('edit_title').focus();
                    })
                    .catch(function (error) {
                        window.alert(error.message);
                    });
            }

            function closeEditModal() {
                editModal.classList.add('hidden');
                editModal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }

            [addModal, editModal].forEach(function (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        modal === addModal ? closeAddModal() : closeEditModal();
                    }
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeAddModal();
                    closeEditModal();
                }
            });

            @if ($errors->any())
                openAddModal();
            @endif
        </script>
    @endpush
@endsection
