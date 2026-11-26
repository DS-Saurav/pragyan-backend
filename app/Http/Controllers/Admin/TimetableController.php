<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PeriodSlot;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    public function index(Request $request)
    {
        $classes = SchoolClass::orderBy('name')->get();
        $selectedClassId = $request->get('class_id');

        $timetableData = [];
        $selectedClass = null;

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        // Fetch dynamic period slots from database, or create defaults if empty
        $periods = PeriodSlot::orderBy('sort_order')->get();
        if ($periods->isEmpty()) {
            $defaults = [
                ['period_code' => 'P1', 'name' => 'Period 1', 'start_time' => '08:30', 'end_time' => '09:15', 'is_break' => false, 'sort_order' => 1],
                ['period_code' => 'P2', 'name' => 'Period 2', 'start_time' => '09:15', 'end_time' => '10:00', 'is_break' => false, 'sort_order' => 2],
                ['period_code' => 'P3', 'name' => 'Period 3', 'start_time' => '10:15', 'end_time' => '11:00', 'is_break' => false, 'sort_order' => 3],
                ['period_code' => 'P4', 'name' => 'Period 4', 'start_time' => '11:00', 'end_time' => '11:45', 'is_break' => false, 'sort_order' => 4],
                ['period_code' => 'P5', 'name' => 'Period 5', 'start_time' => '12:30', 'end_time' => '13:15', 'is_break' => false, 'sort_order' => 5],
            ];
            foreach ($defaults as $d) {
                PeriodSlot::create($d);
            }
            $periods = PeriodSlot::orderBy('sort_order')->get();
        }

        if ($selectedClassId) {
            $selectedClass = SchoolClass::findOrFail($selectedClassId);
            $records = Timetable::where('school_class_id', $selectedClassId)->get();

            foreach ($records as $rec) {
                $timetableData[$rec->day][$rec->period] = [
                    'subject_id' => $rec->subject_id,
                    'teacher_id' => $rec->teacher_id,
                ];
            }
        }

        $subjects = Subject::orderBy('name')->get();
        $teachers = Teacher::orderBy('first_name')->get();

        return response()->json(compact(
            'classes', 'selectedClassId', 'selectedClass',
            'days', 'periods', 'timetableData', 'subjects', 'teachers'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'schedule' => 'required|array',
        ]);

        $classId = $validated['school_class_id'];
        $periods = PeriodSlot::all()->keyBy('period_code');

        foreach ($validated['schedule'] as $day => $periodSlots) {
            foreach ($periodSlots as $periodCode => $data) {
                $subjectId = $data['subject_id'] ?? null;
                $teacherId = $data['teacher_id'] ?? null;

                $slot = $periods[$periodCode] ?? null;
                $timeSlotStr = $slot ? date('H:i', strtotime($slot->start_time)).' - '.date('H:i', strtotime($slot->end_time)) : null;

                if (! empty($subjectId) || ! empty($teacherId)) {
                    Timetable::updateOrCreate(
                        [
                            'school_class_id' => $classId,
                            'day' => $day,
                            'period' => $periodCode,
                        ],
                        [
                            'time_slot' => $timeSlotStr,
                            'subject_id' => $subjectId ?: null,
                            'teacher_id' => $teacherId ?: null,
                        ]
                    );
                } else {
                    Timetable::where('school_class_id', $classId)
                        ->where('day', $day)
                        ->where('period', $periodCode)
                        ->delete();
                }
            }
        }

        return response()->json(["message" => 'Timetable saved successfully!']);
    }

    public function storePeriodSlots(Request $request)
    {
        $validated = $request->validate([
            'periods' => 'required|array|min:1',
            'periods.*.id' => 'nullable|integer',
            'periods.*.period_code' => 'required|string',
            'periods.*.name' => 'required|string',
            'periods.*.start_time' => 'required',
            'periods.*.end_time' => 'required',
            'periods.*.is_break' => 'nullable',
        ]);

        $submittedIds = [];

        foreach ($validated['periods'] as $index => $pData) {
            $isBreak = isset($pData['is_break']) && ($pData['is_break'] == '1' || $pData['is_break'] === true || $pData['is_break'] === 'true');

            // If an ID is provided, update by ID; otherwise update or create by code
            if (! empty($pData['id'])) {
                $slot = PeriodSlot::find($pData['id']);
                if ($slot) {
                    $slot->update([
                        'period_code' => strtoupper(trim($pData['period_code'])),
                        'name' => $pData['name'],
                        'start_time' => $pData['start_time'],
                        'end_time' => $pData['end_time'],
                        'is_break' => $isBreak,
                        'sort_order' => $index + 1,
                    ]);
                    $submittedIds[] = $slot->id;
                }
            } else {
                $slot = PeriodSlot::updateOrCreate(
                    ['period_code' => strtoupper(trim($pData['period_code']))],
                    [
                        'name' => $pData['name'],
                        'start_time' => $pData['start_time'],
                        'end_time' => $pData['end_time'],
                        'is_break' => $isBreak,
                        'sort_order' => $index + 1,
                    ]
                );
                $submittedIds[] = $slot->id;
            }
        }

        // Delete any period slots that were removed from the modal list
        PeriodSlot::whereNotIn('id', $submittedIds)->delete();

        return response()->json(["message" => 'Period slots updated successfully!']);
    }

    public function destroyPeriodSlot(PeriodSlot $period)
    {
        $period->delete();

        return response()->json(["message" => 'Period slot deleted successfully!']);
    }
}
