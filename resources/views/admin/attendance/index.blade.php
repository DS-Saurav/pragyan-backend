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

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{
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

        @if (session('success'))
            <div
                class="mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}
            </div>
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

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Header Row -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        👥 Student Attendance Management
                    </h3>
                    <p class="text-xs text-gray-500">Select class and date to take or update student attendance records.</p>
                </div>
                @if ($selectedClassId && $students->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="markAll('present')"
                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                            ✓ Mark All Present
                        </button>
                        <button type="button" @click="markAll('absent')"
                            class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg shadow transition">
                            ✕ Mark All Absent
                        </button>
                        <form action="{{ route('admin.attendance.copy-yesterday') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">
                            <input type="hidden" name="date" value="{{ $date }}">
                            <button type="submit"
                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow transition">
                                📋 Copy from Previous Day
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Filter Form -->
            <form method="GET" action="{{ route('admin.attendance.index') }}"
                class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 bg-gray-50 p-4 rounded-xl border">
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Class *</label>
                    <select name="class_id" onchange="this.form.submit()" required
                        class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-semibold bg-white">
                        <option value="">— Select class —</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" {{ $selectedClassId == $class->id ? 'selected' : '' }}>
                                {{ $class->name }} {{ $class->section ? '(' . $class->section . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Date *</label>
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" required
                        class="w-full text-xs border border-gray-300 rounded-lg p-2.5 font-semibold bg-white">
                </div>
            </form>

            @if (!$selectedClassId)
                <div
                    class="p-8 text-center bg-gray-50 rounded-xl border border-dashed border-gray-300 text-gray-500 text-xs font-medium">
                    Select a class and date to load the attendance sheet.
                </div>
            @elseif($students->isEmpty())
                <div
                    class="p-8 text-center bg-yellow-50 rounded-xl border border-yellow-200 text-yellow-800 text-xs font-medium">
                    No students found enrolled in this class.
                </div>
            @else
                <!-- Status bar -->
                <div
                    class="flex flex-wrap items-center justify-between gap-4 mb-4 p-3 bg-slate-50 rounded-lg border text-xs">
                    <div>
                        @if ($isSaved)
                            <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded font-bold uppercase text-[10px]">
                                Already Saved — Editing will overwrite
                            </span>
                        @else
                            <span class="px-2.5 py-1 bg-blue-100 text-blue-800 rounded font-bold uppercase text-[10px]">
                                New Entry
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-4 font-bold text-gray-600">
                        <span>Present: <span class="text-emerald-600" x-text="count('present')"></span></span>
                        <span>Absent: <span class="text-rose-600" x-text="count('absent')"></span></span>
                        <span>Late: <span class="text-amber-600" x-text="count('late')"></span></span>
                        <span>Half Day: <span class="text-indigo-600" x-text="count('half_day')"></span></span>
                        <span>Leave: <span class="text-purple-600" x-text="count('leave')"></span></span>
                    </div>
                </div>

                <!-- Attendance table -->
                <form action="{{ route('admin.attendance.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">
                    <input type="hidden" name="date" value="{{ $date }}">

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 w-20">Roll</th>
                                    <th class="px-4 py-3 w-48">ADM #</th>
                                    <th class="px-4 py-3">Student Name</th>
                                    <th class="px-4 py-3 text-right pr-6">Status (P / A / L / H / LV)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($students as $student)
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3 font-mono font-bold text-gray-700">
                                            {{ $student->roll_no ?? '-' }}</td>
                                        <td class="px-4 py-3 font-mono text-gray-600">
                                            {{ $student->adm_number ?? 'ADM-' . $student->id }}</td>
                                        <td class="px-4 py-3 font-bold text-gray-900">{{ $student->first_name }}
                                            {{ $student->last_name }}</td>
                                        <td class="px-4 py-3 text-right pr-6">
                                            <input type="hidden" name="attendance[{{ $student->id }}]"
                                                :value="attendance[{{ $student->id }}]">

                                            <div class="inline-flex rounded-lg p-0.5 bg-gray-100 border gap-1">
                                                @foreach ($statusButtons as [$value, $label, $bg])
                                                    <button type="button"
                                                        @click="attendance[{{ $student->id }}] = '{{ $value }}'"
                                                        :class="attendance[{{ $student->id }}] === '{{ $value }}' ?
                                                            '{{ $bg }} text-white font-bold shadow' :
                                                            'text-gray-600 hover:bg-gray-200'"
                                                        class="px-3 py-1 rounded text-xs transition">{{ $label }}</button>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit"
                            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                            💾 Save Attendance
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
