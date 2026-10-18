@extends('layouts.admin')

@section('content')
    @php
        $n = fn($v) => \App\Services\ResultService::n($v);
        $resBadge = [
            'PASS' => 'bg-emerald-100 text-emerald-800',
            'FAIL' => 'bg-rose-100 text-rose-800',
            'INCOMPLETE' => 'bg-gray-100 text-gray-600',
        ];
    @endphp

    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-3">
            <h2 class="text-xl font-bold text-gray-900">🏆 Class Results</h2>
            <a href="{{ route('admin.marks.index', array_filter(['exam_id' => $exam?->id, 'class_id' => $class?->id])) }}"
                class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-lg">📝 Marks Entry</a>
        </div>

        <form method="GET" action="{{ route('admin.results.index') }}"
            class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-5">
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Exam *</label>
                <select name="exam_id" onchange="this.form.elements['class_id'].value=''; this.form.submit()"
                    class="w-full text-xs border border-gray-300 rounded-lg p-2.5">
                    <option value="">Select exam...</option>
                    @foreach ($exams as $e)
                        <option value="{{ $e->id }}" @selected($exam && $exam->id === $e->id)>{{ $e->name }}
                            ({{ $e->academic_year }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Class *</label>
                <select name="class_id" onchange="this.form.submit()"
                    class="w-full text-xs border border-gray-300 rounded-lg p-2.5" @disabled(!$exam)>
                    <option value="">Select class...</option>
                    @foreach ($classes as $c)
                        <option value="{{ $c->id }}" @selected($class && $class->id === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if (!$data)
            <div class="bg-white p-6 rounded-xl border border-gray-100 text-sm text-gray-500 text-center">Select an exam and
                class to see results.</div>
        @else
            @php $sum = $data['summary']; @endphp

            @if ($exam->status !== 'published')
                <div class="mb-4 bg-amber-100 border border-amber-400 text-amber-800 px-4 py-3 rounded-lg text-sm">
                    <b>Provisional.</b> This exam is not published yet, so these results are visible to admin only.
                </div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
                @foreach ([['Students', $sum['students'], 'bg-slate-900'], ['Passed', $sum['passed'], 'bg-emerald-600'], ['Failed', $sum['failed'], 'bg-rose-600'], ['Pass %', $sum['pass_percent'] . '%', 'bg-sky-600'], ['Highest total', $n($sum['highest']), 'bg-amber-600']] as [$label, $value, $color])
                    <div class="{{ $color }} text-white p-4 rounded-xl shadow-sm">
                        <p class="text-2xl font-bold">{{ $value }}</p>
                        <p class="text-[11px] uppercase tracking-wider opacity-80 font-semibold mt-1">{{ $label }}
                        </p>
                    </div>
                @endforeach
            </div>

            @if ($sum['incomplete'] > 0)
                <div class="mb-4 bg-gray-100 border border-gray-300 text-gray-700 px-4 py-3 rounded-lg text-xs">
                    {{ $sum['incomplete'] }} student(s) are marked <b>INCOMPLETE</b> because some subject marks are not
                    entered yet.
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">
                <div class="flex flex-wrap gap-2 mb-4">
                    <a target="_blank" href="{{ route('admin.results.ledger', [$exam, $class]) }}"
                        class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg">🖨️ Print
                        Result Ledger</a>
                    <a target="_blank" href="{{ route('admin.results.marksheets', [$exam, $class]) }}"
                        class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg">📄
                        Print All Marksheets</a>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                        <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                            <tr>
                                <th class="px-3 py-3">Roll</th>
                                <th class="px-3 py-3">Student</th>
                                @foreach ($data['subjects'] as $es)
                                    <th class="px-3 py-3 text-center whitespace-nowrap">{{ $es->subject->name ?? '' }}<div
                                            class="text-[10px] font-normal opacity-70">/{{ $n($es->full_marks) }}</div>
                                    </th>
                                @endforeach
                                <th class="px-3 py-3 text-center">Total</th>
                                <th class="px-3 py-3 text-center">%</th>
                                <th class="px-3 py-3 text-center">GPA</th>
                                <th class="px-3 py-3 text-center">Rank</th>
                                <th class="px-3 py-3">Result</th>
                                <th class="px-3 py-3 text-right">Marksheet</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($data['rows'] as $row)
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-3 py-3">{{ $row['roll'] }}</td>
                                    <td class="px-3 py-3 font-bold text-gray-900 whitespace-nowrap">{{ $row['name'] }}
                                    </td>
                                    @foreach ($data['subjects'] as $es)
                                        @php $r = $row['subjects'][$es->id]; @endphp
                                        <td
                                            class="px-3 py-3 text-center {{ $r['entered'] && !$r['pass'] ? 'text-rose-600 font-bold' : '' }}">
                                            {{ $r['absent'] ? 'AB' : ($r['entered'] ? $n($r['total']) : '—') }}
                                            @if ($r['entered'])
                                                <span class="block text-[10px] text-gray-400">{{ $r['grade'] }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-3 text-center font-bold">{{ $n($row['total']) }}</td>
                                    <td class="px-3 py-3 text-center">{{ number_format($row['percent'], 1) }}</td>
                                    <td class="px-3 py-3 text-center">{{ number_format($row['gpa'], 2) }}</td>
                                    <td class="px-3 py-3 text-center font-bold">{{ $row['rank'] ?? '—' }}</td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $resBadge[$row['result']] }}">{{ $row['result'] }}</span>
                                        @if ($row['failed'])
                                            <div class="text-[10px] text-rose-600 mt-1">{{ implode(', ', $row['failed']) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <a target="_blank"
                                            href="{{ route('admin.results.marksheet', [$exam, $row['student']]) }}"
                                            class="text-indigo-600 font-bold hover:underline">📄 View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 8 + $data['subjects']->count() }}"
                                        class="px-4 py-8 text-center text-gray-500">No students or subjects found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
