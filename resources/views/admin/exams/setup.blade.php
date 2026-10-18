@extends('layouts.admin')

@section('content')
    @php
        $n = fn($v) => \App\Services\ResultService::n($v);
        $locked = $exam->status === 'published';
        $badge = [
            'pending' => 'bg-gray-100 text-gray-700',
            'draft' => 'bg-amber-100 text-amber-800',
            'submitted' => 'bg-blue-100 text-blue-800',
            'locked' => 'bg-emerald-100 text-emerald-800',
        ];
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
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

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-5 gap-3">
            <div>
                <a href="{{ route('admin.exams.index') }}" class="text-xs text-indigo-600 font-semibold">← Back to Exams</a>
                <h2 class="text-xl font-bold text-gray-900">⚙️ Exam Setup — {{ $exam->name }}
                    <span class="text-sm font-medium text-gray-500">({{ $exam->academic_year }})</span>
                </h2>
                <p class="text-xs text-gray-500">Choose a class, then set each subject's full marks, pass marks and date.</p>
            </div>
            <a href="{{ route('admin.marks.index', ['exam_id' => $exam->id, 'class_id' => $class?->id]) }}"
                class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg">📝 Go to Marks Entry</a>
        </div>

        @if ($locked)
            <div class="mb-4 bg-amber-100 border border-amber-400 text-amber-800 px-4 py-3 rounded-lg text-sm">
                Results are published, so the setup is read-only. Unpublish the exam to change it.
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <div class="flex flex-col lg:flex-row gap-3 lg:items-end justify-between border-b pb-4 mb-4">
                <form method="GET" action="{{ route('admin.exams.setup', $exam) }}">
                    <label class="block text-xs font-bold uppercase mb-1">Class</label>
                    <select name="class_id" onchange="this.form.submit()"
                        class="text-xs border border-gray-300 rounded-lg p-2.5 min-w-[220px]">
                        @foreach ($classes as $c)
                            <option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </form>

                @if ($class && !$locked)
                    <div class="flex flex-wrap items-end gap-2">
                        <form method="POST" action="{{ route('admin.exams.setup.load', $exam) }}">
                            @csrf <input type="hidden" name="class_id" value="{{ $class->id }}">
                            <button type="submit"
                                class="px-3 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg">⬇️
                                Load subjects of this class</button>
                        </form>

                        <form method="POST" action="{{ route('admin.exams.setup.add', $exam) }}" class="flex gap-2">
                            @csrf <input type="hidden" name="class_id" value="{{ $class->id }}">
                            <select name="subject_id" required class="text-xs border border-gray-300 rounded-lg p-2.5">
                                <option value="">Add one subject...</option>
                                @foreach ($available as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit"
                                class="px-3 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg">+
                                Add</button>
                        </form>
                    </div>
                @endif
            </div>

            @if (!$class)
                <p class="text-sm text-gray-500 py-6 text-center">Create a class first.</p>
            @else
                <form id="setupForm" method="POST" action="{{ route('admin.exams.setup.save', $exam) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="class_id" value="{{ $class->id }}">
                    @if ($rows->isNotEmpty() && !$locked)
                        <div class="flex flex-wrap items-end gap-2 mb-3 text-xs">
                            <span class="font-bold text-gray-700">Same time for all subjects:</span>
                            <input type="time" id="allStart" class="border rounded-lg p-2">
                            <span>to</span>
                            <input type="time" id="allEnd" class="border rounded-lg p-2">
                            <button type="button"
                                onclick="
            document.querySelectorAll('.time-start').forEach(i => i.value = document.getElementById('allStart').value);
            document.querySelectorAll('.time-end').forEach(i => i.value = document.getElementById('allEnd').value);"
                                class="px-3 py-2 bg-slate-900 text-white font-bold rounded-lg">Apply</button>
                        </div>
                    @endif
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Subject</th>
                                    <th class="px-4 py-3">Exam Date</th>
                                    <th class="px-4 py-3">Start</th>
                                    <th class="px-4 py-3">End</th>
                                    <th class="px-4 py-3">Theory Full</th>
                                    <th class="px-4 py-3">Theory Pass</th>
                                    <th class="px-4 py-3">Practical Full</th>
                                    <th class="px-4 py-3">Practical Pass</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Marks</th>
                                    <th class="px-4 py-3 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($rows as $r)
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-gray-900">
                                            {{ $r->subject->name ?? '—' }}
                                            <span
                                                class="text-[11px] text-gray-400 font-mono">{{ $r->subject->code ?? '' }}</span>
                                        </td>
                                        <td class="px-4 py-2"><input type="date"
                                                name="rows[{{ $r->id }}][exam_date]"
                                                value="{{ $r->exam_date?->format('Y-m-d') }}" @disabled($locked)
                                                class="text-xs border rounded-lg p-2 w-36"></td>
                                        <td class="px-4 py-2"><input type="time"
                                                name="rows[{{ $r->id }}][start_time]"
                                                value="{{ $r->start_time ? substr($r->start_time, 0, 5) : '' }}"
                                                @disabled($locked)
                                                class="time-start text-xs border rounded-lg p-2 w-28"></td>
                                        <td class="px-4 py-2"><input type="time"
                                                name="rows[{{ $r->id }}][end_time]"
                                                value="{{ $r->end_time ? substr($r->end_time, 0, 5) : '' }}"
                                                @disabled($locked)
                                                class="time-end text-xs border rounded-lg p-2 w-28"></td>
                                        <td class="px-4 py-2"><input type="number" step="0.01" min="0" required
                                                name="rows[{{ $r->id }}][theory_full]"
                                                value="{{ $n($r->theory_full) }}" @disabled($locked)
                                                class="text-xs border rounded-lg p-2 w-20"></td>
                                        <td class="px-4 py-2"><input type="number" step="0.01" min="0"
                                                required name="rows[{{ $r->id }}][theory_pass]"
                                                value="{{ $n($r->theory_pass) }}" @disabled($locked)
                                                class="text-xs border rounded-lg p-2 w-20"></td>
                                        <td class="px-4 py-2"><input type="number" step="0.01" min="0"
                                                name="rows[{{ $r->id }}][practical_full]"
                                                value="{{ $n($r->practical_full) }}" @disabled($locked)
                                                class="text-xs border rounded-lg p-2 w-20"></td>
                                        <td class="px-4 py-2"><input type="number" step="0.01" min="0"
                                                name="rows[{{ $r->id }}][practical_pass]"
                                                value="{{ $n($r->practical_pass) }}" @disabled($locked)
                                                class="text-xs border rounded-lg p-2 w-20"></td>
                                        <td class="px-4 py-3 font-bold">{{ $n($r->full_marks) }}</td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $badge[$r->marks_status] ?? '' }}">{{ $r->marks_status }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @unless ($locked)
                                                <button type="submit" form="remove-{{ $r->id }}"
                                                    class="p-1 hover:text-rose-600" title="Remove"
                                                    onclick="return confirm('Remove this subject? Marks already entered for it will be deleted.');">🗑️</button>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">No subjects yet.
                                            Click "Load subjects of this class".</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <p class="text-[11px] text-gray-400 mt-2">Leave Practical Full at 0 for subjects with no practical or
                        internal marks. A student must reach both the theory and the practical pass marks to pass a subject.
                    </p>

                    @if ($rows->isNotEmpty() && !$locked)
                        <div class="flex justify-end mt-4">
                            <button type="submit"
                                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow">💾
                                Save Setup</button>
                        </div>
                    @endif
                </form>

                @foreach ($rows as $r)
                    <form id="remove-{{ $r->id }}" method="POST"
                        action="{{ route('admin.exams.setup.remove', [$exam, $r]) }}" class="hidden">
                        @csrf @method('DELETE')
                    </form>
                @endforeach

                @if ($rows->isNotEmpty() && !$locked && $classes->count() > 1)
                    <details class="mt-6 border rounded-xl p-4 bg-gray-50">
                        <summary class="text-xs font-bold cursor-pointer">📋 Copy this class's setup to other classes
                        </summary>
                        <form method="POST" action="{{ route('admin.exams.setup.copy', $exam) }}" class="mt-3">
                            @csrf <input type="hidden" name="class_id" value="{{ $class->id }}">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                @foreach ($classes as $c)
                                    @continue($c->id === $class->id)
                                    <label class="flex items-center gap-2"><input type="checkbox" name="target_ids[]"
                                            value="{{ $c->id }}" class="rounded border-gray-300">
                                        {{ $c->name }}</label>
                                @endforeach
                            </div>
                            <p class="text-[11px] text-gray-400 mt-2">Save the setup first. Subjects that already exist in
                                the other classes are not changed.</p>
                            <button type="submit"
                                class="mt-3 px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg">Copy</button>
                        </form>
                    </details>
                @endif
            @endif
        </div>
    </div>
@endsection
