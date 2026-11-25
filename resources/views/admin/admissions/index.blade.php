@extends('layouts.admin')

@section('title', 'Admissions')

@section('content')
    <div class="ui-page" x-data="{ openModal: {{ $errors->any() ? 'true' : 'false' }} }"
        @keydown.escape.window="openModal = false">
        <x-ui.page-header title="Admissions"
            subtitle="Review new applications and admit or reject applicants.">
            <x-slot name="actions">
                <button type="button" @click="openModal = true" class="ui-btn ui-btn-primary">
                    <i class="bi bi-person-plus"></i> New registration
                </button>
            </x-slot>
        </x-ui.page-header>

        <div class="ui-tabs">
            <a href="{{ route('admin.admissions.index') }}"
                @class(['ui-tab', 'ui-tab-active' => ! request('status')])>
                <i class="bi bi-grid"></i> All
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $counts['all'] ?? 0 }}</span>
            </a>
            <a href="{{ route('admin.admissions.index', ['status' => 'registered']) }}"
                @class(['ui-tab', 'ui-tab-active' => request('status') === 'registered'])>
                <i class="bi bi-hourglass-split"></i> Registered
                <span class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-700">{{ $counts['registered'] ?? 0 }}</span>
            </a>
            <a href="{{ route('admin.admissions.index', ['status' => 'admitted']) }}"
                @class(['ui-tab', 'ui-tab-active' => request('status') === 'admitted'])>
                <i class="bi bi-check2-circle"></i> Admitted
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">{{ $counts['admitted'] ?? 0 }}</span>
            </a>
            <a href="{{ route('admin.admissions.index', ['status' => 'rejected']) }}"
                @class(['ui-tab', 'ui-tab-active' => request('status') === 'rejected'])>
                <i class="bi bi-x-circle"></i> Rejected
                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700">{{ $counts['rejected'] ?? 0 }}</span>
            </a>
        </div>

        <section class="ui-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Reg #</th>
                            <th>Applicant</th>
                            <th>Gender</th>
                            <th>DOB</th>
                            <th>Class applied</th>
                            <th>Academic year</th>
                            <th>Parent / Guardian</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($admissions as $admission)
                            <tr>
                                <td class="whitespace-nowrap font-mono text-xs font-semibold text-slate-600">
                                    {{ $admission->reg_number }}
                                </td>
                                <td class="font-semibold text-slate-900">
                                    {{ $admission->first_name }} {{ $admission->last_name }}
                                </td>
                                <td>{{ $admission->gender }}</td>
                                <td class="whitespace-nowrap">
                                    {{ $admission->dob ? \Illuminate\Support\Carbon::parse($admission->dob)->format('Y-m-d') : '—' }}
                                </td>
                                <td>{{ $admission->class_applied }}</td>
                                <td class="whitespace-nowrap">{{ $admission->academic_year }}</td>
                                <td>
                                    <p class="font-medium text-slate-800">{{ $admission->parent_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $admission->mobile }}</p>
                                </td>
                                <td>
                                    @php
                                        $tone = match ($admission->status) {
                                            'admitted' => 'success',
                                            'rejected' => 'danger',
                                            default => 'info',
                                        };
                                    @endphp
                                    <x-ui.badge :tone="$tone" :title="$admission->rejection_reason">{{ $admission->status }}</x-ui.badge>
                                </td>
                                <td class="text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <form action="{{ route('admin.admissions.status', $admission->id) }}" method="POST"
                                            class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="admitted">
                                            <button type="submit" aria-label="Admit {{ $admission->first_name }}"
                                                title="Admit"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-emerald-50 hover:text-emerald-600">
                                                <i class="bi bi-check2-circle"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.admissions.status', $admission->id) }}" method="POST"
                                            class="inline" onsubmit="return confirm('Reject this applicant?');">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <input type="hidden" name="rejection_reason" value="Did not meet criteria.">
                                            <button type="submit" aria-label="Reject {{ $admission->first_name }}"
                                                title="Reject"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10">
                                    <x-ui.empty-state icon="bi-person-plus" title="No registration records"
                                        message="New admission applications will appear here once submitted." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div x-show="openModal" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
            role="dialog" aria-modal="true" aria-labelledby="registrationModalTitle">
            <div @click.outside="openModal = false"
                class="max-h-[92vh] w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <h3 id="registrationModalTitle" class="flex items-center gap-2 text-base font-bold text-slate-900">
                        <i class="bi bi-person-plus text-emerald-600"></i> New registration
                    </h3>
                    <button type="button" @click="openModal = false" aria-label="Close"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>

                <form action="{{ route('admin.admissions.store') }}" method="POST">
                    @csrf
                    <div class="max-h-[70vh] space-y-4 overflow-y-auto p-6">
                        @if ($errors->any())
                            <div class="ui-alert ui-alert-error">
                                <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
                                <div>
                                    <p class="font-semibold">Please fix the following:</p>
                                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="first_name" class="ui-label">First name <span class="text-rose-600">*</span></label>
                                <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}"
                                    required class="ui-input">
                            </div>
                            <div>
                                <label for="last_name" class="ui-label">Last name <span class="text-rose-600">*</span></label>
                                <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}"
                                    required class="ui-input">
                            </div>
                            <div>
                                <label for="gender" class="ui-label">Gender <span class="text-rose-600">*</span></label>
                                <select name="gender" id="gender" required class="ui-input">
                                    <option value="Male" {{ old('gender') === 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>
                            <div>
                                <label for="dob" class="ui-label">Date of birth <span class="text-rose-600">*</span></label>
                                <input type="date" name="dob" id="dob" value="{{ old('dob') }}" required class="ui-input">
                            </div>
                            <div>
                                <label for="class_applied" class="ui-label">Class applied for <span class="text-rose-600">*</span></label>
                                <select name="class_applied" id="class_applied" required class="ui-input">
                                    <option value="">Select class...</option>
                                    @foreach ($classes as $className)
                                        <option value="{{ $className }}"
                                            {{ old('class_applied') == $className ? 'selected' : '' }}>{{ $className }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="academic_year" class="ui-label">Academic year <span class="text-rose-600">*</span></label>
                                <input type="text" name="academic_year" id="academic_year"
                                    value="{{ old('academic_year', current_academic_year()) }}" required class="ui-input">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="address" class="ui-label">Address <span class="text-rose-600">*</span></label>
                                <input type="text" name="address" id="address" value="{{ old('address') }}"
                                    placeholder="e.g. Birendranagar-6, Surkhet" required class="ui-input">
                            </div>
                            <div>
                                <label for="parent_name" class="ui-label">Parent name <span class="text-rose-600">*</span></label>
                                <input type="text" name="parent_name" id="parent_name" value="{{ old('parent_name') }}"
                                    required class="ui-input">
                            </div>
                            <div>
                                <label for="mobile" class="ui-label">Mobile <span class="text-rose-600">*</span></label>
                                <input type="text" name="mobile" id="mobile" value="{{ old('mobile') }}"
                                    placeholder="98XXXXXXXX" required class="ui-input">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                        <button type="button" @click="openModal = false" class="ui-btn ui-btn-secondary">Cancel</button>
                        <button type="submit" class="ui-btn ui-btn-primary">
                            <i class="bi bi-check2"></i> Save registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
