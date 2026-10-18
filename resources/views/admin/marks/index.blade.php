@extends('layouts.admin')

@section('content')
    <style>
        .print-only {
            display: none;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            #printArea,
            #printArea * {
                visibility: visible;
            }

            #printArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .print-only {
                display: block;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>

    @php
        $n = fn($v) => \App\Services\ResultService::n($v);
        $badge = [
            'pending' => 'bg-gray-100 text-gray-700',
            'draft' => 'bg-amber-100 text-amber-800',
            'submitted' => 'bg-blue-100 text-blue-800',
            'locked' => 'bg-emerald-100 text-emerald-800',
        ];
        $readonly = $current && ($current->marks_status === 'locked' || $exam->status === 'published');
        $hasOld = old('marks') !== null;
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        @if (session('success'))
            <div
                class="no-print mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="no-print mb-4 bg-rose-100 border border-rose-400 text-rose-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <h2 class="no-print text-xl font-bold text-gray-900 mb-4">🏅 Results / Marks Entry</h2>

        <!-- Step 1-3: choose exam, class, subject -->
        <form id="filterForm" method="GET" action="{{ route('admin.marks.index') }}"
            class="no-print grid grid-cols-1 md:grid-cols-3 gap-3 bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-5">
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Exam *</label>
                <select name="exam_id" id="selExam" class="w-full text-xs border border-gray-300 rounded-lg p-2.5">
                    <option value="">Select exam...</option>
                    @foreach ($exams as $e)
                        <option value="{{ $e->id }}" @selected($exam && $exam->id === $e->id)>{{ $e->name }}
                            ({{ $e->academic_year }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Class *</label>
                <select name="class_id" id="selClass" class="w-full text-xs border border-gray-300 rounded-lg p-2.5"
                    @disabled(!$exam)>
                    <option value="">Select class...</option>
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Subject *</label>
                <select name="exam_subject_id" id="selSubject"
                    class="w-full text-xs border border-gray-300 rounded-lg p-2.5" @disabled(!$class)>
                    <option value="">Select subject...</option>
                    @foreach ($examSubjects as $es)
                        <option value="{{ $es->id }}" @selected($current && $current->id === $es->id)>
                            {{ $es->subject->name ?? '—' }} (Max {{ $n($es->full_marks) }}) · {{ $es->marks_status }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        @if ($exam && $class && $examSubjects->isEmpty())
            <div class="no-print bg-amber-100 border border-amber-400 text-amber-800 px-4 py-3 rounded-lg text-sm">
                No subjects are set up for this class yet.
                <a class="underline font-bold"
                    href="{{ route('admin.exams.setup', [$exam, 'class_id' => $class->id]) }}">Open exam setup</a>
            </div>
        @elseif (!$current)
            <div class="no-print bg-white p-6 rounded-xl border border-gray-100 text-sm text-gray-500 text-center">
                Select an exam, class and subject to enter marks. Grades are calculated automatically.
            </div>
        @else
            @if ($exam->status === 'published')
                <div class="no-print mb-4 bg-amber-100 border border-amber-400 text-amber-800 px-4 py-3 rounded-lg text-sm">
                    Results are published, so marks are read-only.</div>
            @endif

            <div id="printArea" class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

                <div class="print-only mb-4 text-center">
                    <div class="text-lg font-bold">{{ config('app.name') }}</div>
                    <div class="font-semibold">{{ $exam->name }} ({{ $exam->academic_year }}) — Mark Sheet / Award List
                    </div>
                    <div class="text-sm">Class: {{ $class->name }} &nbsp;|&nbsp; Subject:
                        {{ $current->subject->name ?? '' }}</div>
                </div>

                <div class="no-print flex flex-col lg:flex-row justify-between gap-3 mb-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span
                            class="px-2.5 py-1 rounded-full font-bold uppercase text-[10px] {{ $badge[$current->marks_status] ?? '' }}">{{ $current->marks_status }}</span>
                        <span class="font-bold text-gray-900">{{ $current->subject->name ?? '' }}</span>
                        <span class="text-gray-500">
                            Theory {{ $n($current->theory_full) }} (pass {{ $n($current->theory_pass) }})
                            @if ($current->practical_full > 0)
                                · Practical {{ $n($current->practical_full) }} (pass {{ $n($current->practical_pass) }})
                            @endif
                        </span>
                        <span id="enteredInfo" class="text-gray-400"></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" onclick="window.print()"
                            class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg">🖨️
                            Print A4</button>
                        @unless ($readonly)
                            <button type="submit" form="marksForm" name="action" value="draft"
                                class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-xs font-bold rounded-lg">💾 Save
                                Draft</button>
                            <button type="submit" form="marksForm" name="action" value="submit"
                                class="px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-lg"
                                onclick="return confirm('Submit marks for admin publishing?');">📤 Submit</button>
                            <button type="submit" form="marksForm" name="action" value="lock"
                                class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg"
                                onclick="return confirm('Lock these marks? Nobody can edit them until unlocked.');">🔒
                                Lock</button>
                        @endunless
                        @if ($current->marks_status === 'locked' && $exam->status !== 'published')
                            <button type="submit" form="unlockForm"
                                class="px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg">🔓
                                Unlock</button>
                        @endif
                    </div>
                </div>

                <form id="marksForm" method="POST" action="{{ route('admin.marks.save', $current) }}">
                    @csrf @method('PUT')

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Roll</th>
                                    <th class="px-4 py-3">Adm #</th>
                                    <th class="px-4 py-3">Student</th>
                                    <th class="px-4 py-3 text-center">Absent</th>
                                    @if ($current->theory_full > 0)
                                        <th class="px-4 py-3">Theory (/{{ $n($current->theory_full) }})</th>
                                    @endif
                                    @if ($current->practical_full > 0)
                                        <th class="px-4 py-3">Practical (/{{ $n($current->practical_full) }})</th>
                                    @endif
                                    <th class="px-4 py-3">Total (/{{ $n($current->full_marks) }})</th>
                                    <th class="px-4 py-3">Grade</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($students as $s)
                                    @php
                                        $m = $marks->get($s->id);
                                        $abs = $hasOld ? !empty(old("marks.{$s->id}.absent")) : (bool) $m?->is_absent;
                                        $tv = $hasOld ? old("marks.{$s->id}.theory") : $m?->theory_marks;
                                        $pv = $hasOld ? old("marks.{$s->id}.practical") : $m?->practical_marks;
                                    @endphp
                                    <tr class="mark-row">
                                        <td class="px-4 py-3">{{ \App\Services\ResultService::roll($s) }}</td>
                                        <td class="px-4 py-3 font-mono">{{ \App\Services\ResultService::admission($s) }}
                                        </td>
                                        <td class="px-4 py-3 font-bold text-gray-900">
                                            {{ \App\Services\ResultService::studentName($s) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox" class="abs rounded border-gray-300"
                                                name="marks[{{ $s->id }}][absent]" value="1"
                                                @checked($abs) @disabled($readonly)>
                                        </td>
                                        @if ($current->theory_full > 0)
                                            <td class="px-4 py-2">
                                                <input type="number" step="0.01" min="0"
                                                    max="{{ $current->theory_full }}"
                                                    class="t w-24 text-xs border rounded-lg p-2"
                                                    name="marks[{{ $s->id }}][theory]"
                                                    value="{{ $tv !== null ? $n($tv) : '' }}"
                                                    @disabled($readonly)>
                                            </td>
                                        @endif
                                        @if ($current->practical_full > 0)
                                            <td class="px-4 py-2">
                                                <input type="number" step="0.01" min="0"
                                                    max="{{ $current->practical_full }}"
                                                    class="p w-24 text-xs border rounded-lg p-2"
                                                    name="marks[{{ $s->id }}][practical]"
                                                    value="{{ $pv !== null ? $n($pv) : '' }}"
                                                    @disabled($readonly)>
                                            </td>
                                        @endif
                                        <td class="px-4 py-3 font-bold total">—</td>
                                        <td class="px-4 py-3"><span
                                                class="grade px-2 py-0.5 rounded-md font-bold bg-gray-100 text-gray-500">—</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">No students found
                                            in this class.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>

                <form id="unlockForm" method="POST" action="{{ route('admin.marks.unlock', $current) }}"
                    class="hidden">@csrf</form>

                <p class="no-print text-[11px] text-gray-400 mt-3">
                    Save Draft → keep editing later · Submit → ready for admin publishing (all marks must be filled) · Lock
                    → freeze entries. Only admin can Publish from the Exams page.
                    Press <b>Enter</b> to jump to the next student.
                </p>
            </div>

            <script>
                (function() {
                    var BANDS = @json(\App\Services\GradeScale::BANDS);
                    var TF = {{ (float) $current->theory_full }},
                        TP = {{ (float) $current->theory_pass }};
                    var PF = {{ (float) $current->practical_full }},
                        PP = {{ (float) $current->practical_pass }};

                    function gradeFor(p) {
                        for (var i = 0; i < BANDS.length; i++)
                            if (p >= BANDS[i][0]) return BANDS[i][1];
                        return 'D'; // a pass below the lowest band still gets the lowest grade
                    }

                    function num(el) {
                        if (!el || el.value === '') return null;
                        var v = parseFloat(el.value);
                        return isNaN(v) ? null : v;
                    }

                    function paint(el, cls) {
                        el.className = 'grade px-2 py-0.5 rounded-md font-bold ' + cls;
                    }

                    function recalc(tr) {
                        var abs = tr.querySelector('.abs').checked;
                        var t = tr.querySelector('.t'),
                            p = tr.querySelector('.p');
                        var total = tr.querySelector('.total'),
                            grade = tr.querySelector('.grade');

                        [t, p].forEach(function(el) {
                            if (el && !el.hasAttribute('data-ro')) el.disabled = abs ||
                                {{ $readonly ? 'true' : 'false' }};
                        });

                        if (abs) {
                            total.textContent = 'AB';
                            grade.textContent = 'AB';
                            paint(grade, 'bg-gray-200 text-gray-700');
                            return 0;
                        }

                        var tv = num(t),
                            pv = num(p);
                        if (t) t.classList.toggle('border-rose-500', tv !== null && (tv < 0 || tv > TF));
                        if (p) p.classList.toggle('border-rose-500', pv !== null && (pv < 0 || pv > PF));

                        if (tv === null && pv === null) {
                            total.textContent = '—';
                            grade.textContent = '—';
                            paint(grade, 'bg-gray-100 text-gray-500');
                            return 0;
                        }

                        var sum = (tv || 0) + (pv || 0);
                        var pct = sum / (TF + PF) * 100;
                        var pass = (TF <= 0 || (tv !== null && tv >= TP)) && (PF <= 0 || (pv !== null && pv >= PP));

                        total.textContent = Math.round(sum * 100) / 100;
                        grade.textContent = pass ? gradeFor(pct) : 'NG';
                        paint(grade, pass ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800');
                        return 1;
                    }

                    function refreshAll() {
                        var rows = document.querySelectorAll('.mark-row'),
                            done = 0;
                        rows.forEach(function(tr) {
                            done += recalc(tr) || (tr.querySelector('.abs').checked ? 1 : 0);
                        });
                        var info = document.getElementById('enteredInfo');
                        if (info) info.textContent = '· Entered ' + done + ' / ' + rows.length;
                    }

                    document.addEventListener('input', function(e) {
                        if (e.target.closest('.mark-row')) refreshAll();
                    });
                    document.addEventListener('change', function(e) {
                        if (e.target.closest('.mark-row')) refreshAll();
                    });

                    document.addEventListener('keydown', function(e) {
                        if (e.key !== 'Enter' || !e.target.matches('.t, .p')) return;
                        e.preventDefault();
                        var cls = e.target.classList.contains('t') ? '.t' : '.p';
                        var row = e.target.closest('tr').nextElementSibling;
                        while (row && row.querySelector(cls) && row.querySelector(cls).disabled) row = row
                            .nextElementSibling;
                        if (row && row.querySelector(cls)) {
                            row.querySelector(cls).focus();
                            row.querySelector(cls).select();
                        }
                    });

                    refreshAll();
                })();
            </script>
        @endif
    </div>

    <script>
        (function() {
            var form = document.getElementById('filterForm');
            document.getElementById('selExam').addEventListener('change', function() {
                document.getElementById('selClass').value = '';
                document.getElementById('selSubject').value = '';
                form.submit();
            });
            document.getElementById('selClass').addEventListener('change', function() {
                document.getElementById('selSubject').value = '';
                form.submit();
            });
            document.getElementById('selSubject').addEventListener('change', function() {
                form.submit();
            });
        })();
    </script>
@endsection
