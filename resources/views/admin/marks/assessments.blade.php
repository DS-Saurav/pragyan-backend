@extends('layouts.admin')

@section('content')
    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if (session('success'))
            <div class="mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
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

        <h2 class="text-xl font-bold text-gray-900 mb-1"><i class="bi bi-stars"></i> Assessment Indicators</h2>
        <p class="text-xs text-gray-500 mb-4">
            One rating per indicator, student and examination — from the fixed scale
            {{ implode(' / ', array_values(config('school.assessments.scale'))) }}.
            These ratings are not subject grades and do not affect GPA.
        </p>

        <!-- Step 1-2: choose exam and class (assessment ratings belong to the exam) -->
        <form method="GET" action="{{ route('admin.marks.assessments') }}"
            class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-5">
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Examination *</label>
                <select name="exam_id" class="w-full text-xs border border-gray-300 rounded-lg p-2.5">
                    <option value="">Select examination...</option>
                    @foreach ($exams as $e)
                        <option value="{{ $e->id }}" @selected($exam && $exam->id === $e->id)>{{ $e->name }}
                            ({{ $e->academic_year }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Class *</label>
                <select name="class_id" class="w-full text-xs border border-gray-300 rounded-lg p-2.5"
                    @disabled(!$exam)>
                    <option value="">Select class...</option>
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold">Open
                    Assessments</button>
            </div>
        </form>

        @if ($exam && $indicators === [])
            <div class="bg-amber-100 border border-amber-400 text-amber-800 px-4 py-3 rounded-lg text-sm">
                No assessment indicators are configured yet. An administrator must add them under the marksheet
                layout editor.
            </div>
        @elseif ($exam && $class && $students->isEmpty())
            <div class="bg-amber-100 border border-amber-400 text-amber-800 px-4 py-3 rounded-lg text-sm">
                No students are enrolled in this class.
            </div>
        @elseif ($exam && $class && $indicators !== [])
            <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100">
                <!-- Which examination these ratings belong to (kept visible at all times) -->
                <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">
                            Assessment Indicators — {{ $exam->name }}
                        </h3>
                        <p class="text-[11px] text-gray-500">
                            Examination: <span class="font-bold">{{ $exam->name }}</span>
                            ({{ $exam->academic_year }}) · Class: <span class="font-bold">{{ $class->name }}</span>
                            @if ($exam->status === 'published')
                                · <span class="text-emerald-700 font-bold">Results published (read-only)</span>
                            @endif
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.marks.assessments.save', $exam) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="class_id" value="{{ $class->id }}">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-left border-b border-gray-200">
                                    <th class="p-2 uppercase text-[10px] font-bold text-gray-500">Roll</th>
                                    <th class="p-2 uppercase text-[10px] font-bold text-gray-500">Student</th>
                                    @foreach ($indicators as $indicator)
                                        <th class="p-2 uppercase text-[10px] font-bold text-gray-500">
                                            {{ $indicator['label'] }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $student)
                                    <tr class="border-b border-gray-100">
                                        <td class="p-2 font-bold text-gray-500">{{ $student->roll_no ?? '-' }}</td>
                                        <td class="p-2 font-bold text-gray-900 whitespace-nowrap">
                                            {{ \App\Services\ResultService::studentName($student) }}</td>
                                        @foreach ($indicators as $indicator)
                                            <td class="p-2">
                                                <select
                                                    name="ratings[{{ $student->id }}][{{ $indicator['key'] }}]"
                                                    class="border-gray-300 rounded text-xs"
                                                    @if ($exam->status === 'published') disabled @endif>
                                                    <option value="">— Not set —</option>
                                                    @foreach (config('school.assessments.scale') as $key => $label)
                                                        <option value="{{ $key }}"
                                                            @selected(($ratings[$student->id][$indicator['key']] ?? '') === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($exam->status !== 'published')
                        <div class="flex justify-end pt-2">
                            <button type="submit"
                                class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-bold">Save
                                Assessments</button>
                        </div>
                    @else
                        <p class="text-[11px] text-gray-500">
                            Results are published; unpublish the exam to change these assessments.
                        </p>
                    @endif
                </form>
            </div>
        @endif
    </div>
@endsection
