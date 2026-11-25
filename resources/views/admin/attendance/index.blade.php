@extends('layouts.admin')

@section('title', 'Attendance')

@section('content')
    @php
        $statusButtons = [
            ['present', 'P', 'bg-emerald-600'],
            ['absent', 'A', 'bg-rose-600'],
            ['late', 'L', 'bg-amber-500'],
            ['half_day', 'H', 'bg-indigo-600'],
            ['leave', 'LV', 'bg-purple-600'],
        ];
    @endphp

    <div class="ui-page" x-data="{
        attendance: @js((object) $attendanceMap),
        markAll(status) {
            for (let id in this.attendance) {
                this.attendance[id] = status;
            }
        },
        count(status) {
            return Object.values(this.attendance).filter(v => v === status).length;
        }
    }">
        <x-ui.page-header title="Student Attendance"
            subtitle="Select a class and date to take or update student attendance records.">
            @if ($selectedClassId && $students->isNotEmpty())
                <x-slot name="actions">
                    <button type="button" @click="markAll('present')" class="ui-btn ui-btn-secondary ui-btn-sm">
                        <i class="bi bi-check2-all"></i> Mark all present
                    </button>
                    <button type="button" @click="markAll('absent')" class="ui-btn ui-btn-secondary ui-btn-sm">
                        <i class="bi bi-x-lg"></i> Mark all absent
                    </button>
                    <form action="{{ route('admin.attendance.copy-yesterday') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">
                        <input type="hidden" name="date" value="{{ $date }}">
                        <button type="submit" class="ui-btn ui-btn-secondary ui-btn-sm">
                            <i class="bi bi-clipboard-check"></i> Copy previous day
                        </button>
                    </form>
                </x-slot>
            @endif
        </x-ui.page-header>

        <section class="ui-card">
            <div class="ui-card-body">
                <form method="GET" action="{{ route('admin.attendance.index') }}"
                    class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                    <div>
                        <label for="class_id" class="ui-label">Class <span class="text-rose-600">*</span></label>
                        <select name="class_id" id="class_id" onchange="this.form.submit()" required class="ui-input">
                            <option value="">Select class...</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" {{ $selectedClassId == $class->id ? 'selected' : '' }}>
                                    {{ $class->name }} {{ $class->section ? '(' . $class->section . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="date" class="ui-label">Date <span class="text-rose-600">*</span></label>
                        <input type="date" name="date" id="date" value="{{ $date }}" onchange="this.form.submit()"
                            required class="ui-input">
                    </div>
                </form>
            </div>
        </section>

        @if (!$selectedClassId)
            <x-ui.empty-state icon="bi-person-check" title="Select a class"
                message="Choose a class and date to load the attendance sheet." />
        @elseif ($students->isEmpty())
            <x-ui.empty-state icon="bi-people" title="No students enrolled"
                message="No students are currently enrolled in this class." />
        @else
            <section class="ui-card overflow-hidden">
                <div class="ui-card-header">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($isSaved)
                            <x-ui.badge tone="warning">Already saved — editing will overwrite</x-ui.badge>
                        @else
                            <x-ui.badge tone="info">New entry</x-ui.badge>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-4 text-xs font-semibold text-slate-500">
                        <span>Present: <span class="text-emerald-600" x-text="count('present')"></span></span>
                        <span>Absent: <span class="text-rose-600" x-text="count('absent')"></span></span>
                        <span>Late: <span class="text-amber-600" x-text="count('late')"></span></span>
                        <span>Half day: <span class="text-indigo-600" x-text="count('half_day')"></span></span>
                        <span>Leave: <span class="text-violet-600" x-text="count('leave')"></span></span>
                    </div>
                </div>

                <form action="{{ route('admin.attendance.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">
                    <input type="hidden" name="date" value="{{ $date }}">

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th class="w-20">Roll</th>
                                    <th class="w-44">Admission #</th>
                                    <th>Student name</th>
                                    <th class="pr-6 text-right">Status (P / A / L / H / LV)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    <tr>
                                        <td class="font-mono font-semibold text-slate-700">{{ $student->roll_no ?? '—' }}</td>
                                        <td class="font-mono text-slate-600">{{ $student->adm_number ?? 'ADM-' . $student->id }}</td>
                                        <td class="font-semibold text-slate-900">
                                            {{ $student->first_name }} {{ $student->last_name }}
                                        </td>
                                        <td class="pr-6 text-right">
                                            <input type="hidden" name="attendance[{{ $student->id }}]"
                                                :value="attendance[{{ $student->id }}]">

                                            <div class="inline-flex gap-1 rounded-lg border border-slate-200 bg-slate-100 p-0.5">
                                                @foreach ($statusButtons as [$value, $label, $bg])
                                                    <button type="button"
                                                        @click="attendance[{{ $student->id }}] = '{{ $value }}'"
                                                        :class="attendance[{{ $student->id }}] === '{{ $value }}' ?
                                                            '{{ $bg }} text-white font-bold shadow' :
                                                            'text-slate-600 hover:bg-white'"
                                                        class="rounded px-3 py-1 text-xs transition">{{ $label }}</button>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                        <button type="submit" class="ui-btn ui-btn-primary">
                            <i class="bi bi-save"></i> Save attendance
                        </button>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
