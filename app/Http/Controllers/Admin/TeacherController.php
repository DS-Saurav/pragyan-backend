<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $statusTab = $request->get('tab', 'all');
        $query = Teacher::query();

        if (in_array($statusTab, ['active', 'inactive', 'suspended'], true)) {
            $query->where('status', $statusTab);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $teachers = $query->latest()->get()->map(fn (Teacher $t) => $this->formatTeacher($t));

        $all = Teacher::all();
        $totalAssignments = $all->sum(fn (Teacher $t) => is_array($t->assignments) ? count($t->assignments) : 0);

        return response()->json([
            'teachers' => $teachers,
            'counts' => [
                'all' => $all->count(),
                'active' => $all->where('status', 'active')->count(),
                'inactive' => $all->where('status', 'inactive')->count(),
                'suspended' => $all->where('status', 'suspended')->count(),
                'assignments' => $totalAssignments,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'nullable|string|max:100|unique:teachers,username',
            'email' => 'required|email|unique:teachers,email',
            'phone' => 'nullable|string|max:20',
            'department' => 'required|string|max:100',
            'specialization' => 'nullable|string|max:100',
            'class_teacher_of' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        if (empty($validated['username'])) {
            $validated['username'] = '@'.strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $validated['first_name'].$validated['last_name']));
        }

        $teacher = Teacher::create($validated);

        return response()->json(['teacher' => $this->formatTeacher($teacher)], 201);
    }

    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'nullable|string|max:100|unique:teachers,username,'.$teacher->id,
            'email' => 'required|email|unique:teachers,email,'.$teacher->id,
            'phone' => 'nullable|string|max:20',
            'department' => 'required|string|max:100',
            'specialization' => 'nullable|string|max:100',
            'class_teacher_of' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $teacher->update($validated);

        return response()->json(['teacher' => $this->formatTeacher($teacher->fresh())]);
    }

    public function assign(Request $request, Teacher $teacher): JsonResponse
    {
        $validated = $request->validate([
            'class_name' => 'required|string',
            'subject_name' => 'required|string',
        ]);

        $assignments = $teacher->assignments ?? [];
        $assignments[] = [
            'id' => uniqid(),
            'class' => $validated['class_name'],
            'subject' => $validated['subject_name'],
        ];
        $teacher->update(['assignments' => $assignments]);

        return response()->json(['teacher' => $this->formatTeacher($teacher->fresh())]);
    }

    public function removeAssignment(Teacher $teacher, string $assignmentId): JsonResponse
    {
        $assignments = collect($teacher->assignments ?? [])
            ->reject(fn ($item) => ($item['id'] ?? null) === $assignmentId)
            ->values()
            ->all();
        $teacher->update(['assignments' => $assignments]);

        return response()->json(['teacher' => $this->formatTeacher($teacher->fresh())]);
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        $teacher->delete();

        return response()->json(['deleted' => true]);
    }

    public function exportCsv(): JsonResponse
    {
        return response()->json(['teachers' => Teacher::all()]);
    }

    private function formatTeacher(Teacher $t): array
    {
        $assignments = is_array($t->assignments) ? $t->assignments : [];
        $classesCount = count(array_unique(array_filter(array_column($assignments, 'class'))));

        return [
            'id' => $t->id,
            'firstName' => $t->first_name,
            'lastName' => $t->last_name,
            'username' => $t->username ?? '@'.strtolower($t->first_name),
            'email' => $t->email,
            'phone' => $t->phone ?? '',
            'department' => $t->department ?? '',
            'specialization' => $t->specialization ?? '',
            'classesCount' => $classesCount,
            'assignmentsCount' => count($assignments),
            'classTeacherOf' => $t->class_teacher_of ?: null,
            'status' => $t->status ?? 'active',
        ];
    }
}
