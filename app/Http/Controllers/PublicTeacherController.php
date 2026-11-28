<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class PublicTeacherController extends Controller
{
    public function index(): JsonResponse
    {
        $teachers = Teacher::visible()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Teacher $teacher): array => $this->formatTeacher($teacher))
            ->values();

        $subjects = $teachers
            ->pluck('specialization')
            ->filter(fn (?string $value): bool => filled($value))
            ->unique()
            ->values();

        return response()->json([
            'teachers' => $teachers,
            'subjects' => $subjects,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $teacher = Teacher::visible()
            ->where('slug', $slug)
            ->firstOrFail();

        $otherTeachers = Teacher::visible()
            ->where('id', '!=', $teacher->id)
            ->orderBy('first_name')
            ->limit(4)
            ->get()
            ->map(fn (Teacher $item): array => $this->formatTeacher($item))
            ->values();

        return response()->json([
            'teacher' => $this->formatTeacher($teacher),
            'otherTeachers' => $otherTeachers,
        ]);
    }

    /** @return array<string, mixed> */
    private function formatTeacher(Teacher $teacher): array
    {
        $experienceYears = null;
        if ($teacher->joining_date) {
            $years = (int) Carbon::parse($teacher->joining_date)->diffInYears(now());
            $experienceYears = max(0, $years);
        }

        return [
            'id' => $teacher->id,
            'slug' => $teacher->slug,
            'firstName' => $teacher->first_name,
            'middleName' => $teacher->middle_name ?? '',
            'lastName' => $teacher->last_name,
            'fullName' => $teacher->full_name,
            'gender' => $teacher->gender ?? '',
            'qualification' => $teacher->qualification ?? '',
            'specialization' => $teacher->specialization ?? '',
            'joiningDate' => $teacher->joining_date?->format('Y-m-d') ?? '',
            'experienceYears' => $experienceYears,
            'profileImage' => $teacher->profileImageUrl(),
            'bio' => $teacher->bio ?? '',
        ];
    }
}
