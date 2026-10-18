@extends('layouts.admin')

@section('content')
    <div class="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8" x-data="{
        showPeriodsModal: false,
        slots: {{ json_encode(
            $periods->map(
                fn($p) => [
                    'id' => $p->id,
                    'period_code' => $p->period_code,
                    'name' => $p->name,
                    'start_time' => date('H:i', strtotime($p->start_time)),
                    'end_time' => date('H:i', strtotime($p->end_time)),
                    'is_break' => (bool) $p->is_break,
                ],
            ),
        ) }},
        addSlot() {
            let nextNum = this.slots.length + 1;
            this.slots.push({
                id: null,
                period_code: 'P' + nextNum,
                name: 'Period ' + nextNum,
                start_time: '12:00',
                end_time: '12:45',
                is_break: false
            });
        },
        removeSlot(index) {
            if (this.slots.length > 1) {
                this.slots.splice(index, 1);
            } else {
                alert('You must have at least one period slot.');
            }
        }
    }">

        @if (session('success'))
            <div
                class="mb-4 bg-emerald-100 border border-emerald-400 text-emerald-700 px-4 py-3 rounded-lg text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-xl p-6 border border-gray-100">

            <!-- Header Row -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        📅 Timetable & Period Management
                    </h3>
                    <p class="text-xs text-gray-500">Add/remove daily periods, set custom timings, and assign subjects and
                        teachers per class.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="showPeriodsModal = true"
                        class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-lg shadow-sm transition">
                        ⚙️ Manage Period Slots (<span x-text="slots.length"></span>)
                    </button>
                    @if ($selectedClassId)
                        <button onclick="window.print();"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-bold rounded-lg shadow transition">
                            🖨️ Print
                        </button>
                        <button type="submit" form="timetableForm"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                            💾 Save Timetable
                        </button>
                    @endif
                </div>
            </div>

            <!-- Class Selector -->
            <form method="GET" action="{{ route('admin.timetable.index') }}" class="mb-6">
                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Class *</label>
                <select name="class_id" onchange="this.form.submit()"
                    class="w-full text-xs border border-gray-300 rounded-lg p-3 font-semibold focus:ring-2 focus:ring-indigo-500">
                    <option value="">— Select class —</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" {{ $selectedClassId == $class->id ? 'selected' : '' }}>
                            {{ $class->name }} {{ $class->section ? '(' . $class->section . ')' : '' }}
                        </option>
                    @endforeach
                </select>
            </form>

            @if (!$selectedClassId)
                <div
                    class="p-8 text-center bg-gray-50 rounded-xl border border-dashed border-gray-300 text-gray-500 text-xs font-medium">
                    Select a class above to view or edit its weekly timetable. You can add or remove periods using "Manage
                    Period Slots".
                </div>
            @else
                <!-- Timetable Matrix Form -->
                <form id="timetableForm" action="{{ route('admin.timetable.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="school_class_id" value="{{ $selectedClassId }}">

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                            <thead class="bg-slate-900 text-white uppercase font-bold tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 w-40">Period & Time</th>
                                    @foreach ($days as $day)
                                        <th class="px-4 py-3">{{ $day }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($periods as $p)
                                    <tr class="hover:bg-gray-50/50 transition {{ $p->is_break ? 'bg-amber-50/60' : '' }}">
                                        <td
                                            class="px-4 py-3 font-bold text-gray-800 align-middle {{ $p->is_break ? 'bg-amber-100/50' : 'bg-slate-50' }}">
                                            <div class="text-indigo-600 font-extrabold flex items-center gap-1">
                                                {{ $p->name }} ({{ $p->period_code }})
                                                @if ($p->is_break)
                                                    <span
                                                        class="text-[9px] bg-amber-200 text-amber-900 px-1.5 py-0.5 rounded font-bold uppercase">Break</span>
                                                @endif
                                            </div>
                                            <div class="text-[10px] text-gray-500 font-mono mt-0.5">
                                                {{ date('H:i', strtotime($p->start_time)) }} -
                                                {{ date('H:i', strtotime($p->end_time)) }}
                                            </div>
                                        </td>

                                        @if ($p->is_break)
                                            <td colspan="{{ count($days) }}"
                                                class="px-4 py-3 text-center text-amber-800 font-bold uppercase tracking-widest text-xs bg-amber-50">
                                                ☕ {{ $p->name }}
                                            </td>
                                        @else
                                            @foreach ($days as $day)
                                                @php
                                                    $currSubject =
                                                        $timetableData[$day][$p->period_code]['subject_id'] ?? '';
                                                    $currTeacher =
                                                        $timetableData[$day][$p->period_code]['teacher_id'] ?? '';
                                                @endphp
                                                <td class="px-3 py-3 border-r last:border-r-0 align-top space-y-2">
                                                    <select
                                                        name="schedule[{{ $day }}][{{ $p->period_code }}][subject_id]"
                                                        class="w-full text-[11px] border border-gray-300 rounded-md p-1.5 bg-white font-medium focus:ring-1 focus:ring-indigo-500">
                                                        <option value="">— Select Subject —</option>
                                                        @foreach ($subjects as $subj)
                                                            <option value="{{ $subj->id }}"
                                                                {{ $currSubject == $subj->id ? 'selected' : '' }}>
                                                                {{ $subj->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>

                                                    <select
                                                        name="schedule[{{ $day }}][{{ $p->period_code }}][teacher_id]"
                                                        class="w-full text-[11px] border border-gray-300 rounded-md p-1.5 bg-white text-gray-600 focus:ring-1 focus:ring-indigo-500">
                                                        <option value="">— Select Teacher —</option>
                                                        @foreach ($teachers as $tch)
                                                            <option value="{{ $tch->id }}"
                                                                {{ $currTeacher == $tch->id ? 'selected' : '' }}>
                                                                {{ $tch->first_name }} {{ $tch->last_name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                            @endforeach
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit"
                            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow transition">
                            Save Timetable Changes
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <!-- Manage Dynamic Period Slots Modal -->
        <div x-show="showPeriodsModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm"
            style="display: none;">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl p-6 max-h-[90vh] flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <h3 class="text-md font-bold text-gray-900">Manage Daily Period Slots & Durations</h3>
                    <button @click="showPeriodsModal = false"
                        class="text-xl text-gray-500 hover:text-black">&times;</button>
                </div>

                <form action="{{ route('admin.timetable.periods.store') }}" method="POST"
                    class="flex-1 flex flex-col overflow-hidden">
                    @csrf
                    <div class="flex-1 overflow-y-auto space-y-3 pr-2 mb-4">
                        <template x-for="(slot, index) in slots" :key="index">
                            <div class="grid grid-cols-12 gap-2 items-center bg-gray-50 p-3 rounded-lg border relative">
                                <input type="hidden" :name="'periods[' + index + '][id]'" :value="slot.id">

                                <div class="col-span-2">
                                    <label class="block text-[10px] uppercase font-bold text-gray-500">Code</label>
                                    <input type="text" :name="'periods[' + index + '][period_code]'"
                                        x-model="slot.period_code" required
                                        class="w-full text-xs border rounded p-1.5 font-mono uppercase">
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-[10px] uppercase font-bold text-gray-500">Period Name</label>
                                    <input type="text" :name="'periods[' + index + '][name]'" x-model="slot.name" required
                                        class="w-full text-xs border rounded p-1.5">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-[10px] uppercase font-bold text-gray-500">Start</label>
                                    <input type="time" :name="'periods[' + index + '][start_time]'" x-model="slot.start_time"
                                        required class="w-full text-xs border rounded p-1.5">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-[10px] uppercase font-bold text-gray-500">End</label>
                                    <input type="time" :name="'periods[' + index + '][end_time]'" x-model="slot.end_time"
                                        required class="w-full text-xs border rounded p-1.5">
                                </div>
                                <div class="col-span-2 text-center">
                                    <label class="block text-[10px] uppercase font-bold text-gray-500">Break?</label>
                                    <input type="checkbox" :name="'periods[' + index + '][is_break]'" value="1"
                                        x-model="slot.is_break" class="mt-1 rounded text-amber-600 focus:ring-amber-500">
                                </div>
                                <div class="col-span-1 text-right">
                                    <button type="button" @click="removeSlot(index)"
                                        class="text-rose-500 hover:text-rose-700 font-bold p-1 text-base"
                                        title="Remove Period">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="flex justify-between items-center pt-4 border-t">
                        <button type="button" @click="addSlot()"
                            class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-lg shadow-sm transition">
                            + Add Period Slot
                        </button>

                        <div class="flex space-x-2">
                            <button type="button" @click="showPeriodsModal = false"
                                class="px-4 py-2 bg-gray-200 text-xs font-bold rounded-lg">Cancel</button>
                            <button type="submit"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg">Save
                                Period Slots</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
