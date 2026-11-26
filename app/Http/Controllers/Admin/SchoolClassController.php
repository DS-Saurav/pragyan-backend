<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function index(): JsonResponse
    {
        $academicYear = function_exists('current_academic_year') ? current_academic_year() : '2082-2083';

        $classes = SchoolClass::query()
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->map(function (SchoolClass $c, int $idx) use ($academicYear) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'section' => $c->section,
                    'academicYear' => $academicYear,
                    'room' => 'Room '.(101 + $idx),
                    'capacity' => 35,
                    'strength' => Student::where('school_class_id', $c->id)->count(),
                    'classTeacher' => 'Assigned Teacher',
                    'status' => 'active',
                ];
            });

        return response()->json(['classes' => $classes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'section' => 'required|string|max:50',
        ]);

        $class = SchoolClass::create($validated);

        return response()->json([
            'schoolClass' => [
                'id' => $class->id,
                'name' => $class->name,
                'section' => $class->section,
                'academicYear' => function_exists('current_academic_year') ? current_academic_year() : '2082-2083',
                'room' => 'Room 101',
                'capacity' => 35,
                'strength' => 0,
                'classTeacher' => 'Unassigned',
                'status' => 'active',
            ],
        ], 201);
    }

    public function destroy($id): JsonResponse
    {
        $class = SchoolClass::findOrFail($id);
        $class->delete();

        return response()->json(['deleted' => true]);
    }
}
