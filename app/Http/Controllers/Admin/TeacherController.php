<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $statusTab = $request->get('tab', 'all');
        $query = Teacher::query();

        if (in_array($statusTab, ['active', 'inactive'], true)) {
            $query->where('status', $statusTab);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('qualification', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $teachers = $query->latest()->get()->map(fn (Teacher $t) => $this->formatTeacher($t));

        $all = Teacher::all();

        return response()->json([
            'teachers' => $teachers,
            'counts' => [
                'all' => $all->count(),
                'active' => $all->where('status', 'active')->count(),
                'inactive' => $all->where('status', 'inactive')->count(),
                'visible' => $all->where('is_visible', true)->count(),
            ],
        ]);
    }

    public function show(Teacher $teacher): JsonResponse
    {
        return response()->json(['teacher' => $this->formatTeacher($teacher)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTeacher($request);

        $validated['slug'] = $this->resolveUniqueSlug(
            $validated['slug'] ?? null,
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name']
        );
        $validated['is_visible'] = array_key_exists('is_visible', $validated)
            ? (bool) $validated['is_visible']
            : true;

        unset($validated['remove_profile_image']);

        if ($request->hasFile('profile_image')) {
            $validated['profile_image'] = $this->storeProfileImage($request->file('profile_image'));
        } else {
            unset($validated['profile_image']);
        }

        $teacher = Teacher::create($validated);

        return response()->json(['teacher' => $this->formatTeacher($teacher)], 201);
    }

    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $validated = $this->validateTeacher($request, $teacher);

        if (array_key_exists('slug', $validated) && filled($validated['slug'])) {
            $validated['slug'] = $this->resolveUniqueSlug(
                $validated['slug'],
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['last_name'],
                $teacher->id
            );
        } elseif (! $teacher->slug) {
            $validated['slug'] = $this->resolveUniqueSlug(
                null,
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['last_name'],
                $teacher->id
            );
        }

        $removeImage = (bool) ($validated['remove_profile_image'] ?? false);
        unset($validated['remove_profile_image']);

        if ($request->hasFile('profile_image')) {
            $newPath = $this->storeProfileImage($request->file('profile_image'));
            $this->deleteStoredProfileImage($teacher->profile_image);
            $validated['profile_image'] = $newPath;
        } elseif ($removeImage) {
            $this->deleteStoredProfileImage($teacher->profile_image);
            $validated['profile_image'] = null;
        } else {
            unset($validated['profile_image']);
        }

        $teacher->update($validated);

        return response()->json(['teacher' => $this->formatTeacher($teacher->fresh())]);
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        $this->deleteStoredProfileImage($teacher->profile_image);
        $teacher->delete();

        return response()->json(['deleted' => true]);
    }

    public function exportCsv(): JsonResponse
    {
        return response()->json(['teachers' => Teacher::all()]);
    }

    private function validateTeacher(Request $request, ?Teacher $teacher = null): array
    {
        $this->guardPhpUploadError($request);

        return $request->validate([
            'slug' => [
                'nullable',
                'string',
                'max:180',
                Rule::unique('teachers', 'slug')->ignore($teacher?->id),
            ],
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string',
            'qualification' => 'nullable|string|max:150',
            'specialization' => 'nullable|string|max:150',
            'joining_date' => 'nullable|date',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_profile_image' => 'sometimes|boolean',
            'bio' => 'nullable|string',
            'is_visible' => 'sometimes|boolean',
            'status' => 'required|string|in:active,inactive',
        ], [
            'profile_image.image' => 'Profile image must be a valid image file.',
            'profile_image.mimes' => 'Profile image must be a JPG, JPEG, PNG, or WEBP file.',
            'profile_image.max' => 'Profile image must not be larger than 5 MB.',
        ]);
    }

    private function guardPhpUploadError(Request $request): void
    {
        $file = $request->file('profile_image');
        if ($file && ! $file->isValid()) {
            $message = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE => sprintf(
                    'Profile image exceeds the server upload limit (%s). Please choose a smaller image or increase upload_max_filesize in php.ini.',
                    ini_get('upload_max_filesize') ?: '2M'
                ),
                UPLOAD_ERR_FORM_SIZE => 'Profile image exceeds the maximum allowed form size.',
                UPLOAD_ERR_PARTIAL => 'Profile image was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temporary upload folder is missing.',
                UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded profile image to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the profile image upload.',
                default => 'Profile image failed to upload: '.$file->getErrorMessage(),
            };

            throw ValidationException::withMessages(['profile_image' => [$message]]);
        }
    }

    private function storeProfileImage(\Illuminate\Http\UploadedFile $file): string
    {
        try {
            $path = $file->store('teachers/profiles', 'r2');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'profile_image' => ['Cloudflare R2 upload failed: '.$e->getMessage()],
            ]);
        }

        if (! $path) {
            throw ValidationException::withMessages([
                'profile_image' => ['Cloudflare R2 upload failed: storage returned an empty path.'],
            ]);
        }

        return $path;
    }

    private function deleteStoredProfileImage(?string $path): void
    {
        $trimmed = trim((string) $path);
        if ($trimmed === '' || str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, '/')) {
            return;
        }

        if (Storage::disk('r2')->exists($trimmed)) {
            Storage::disk('r2')->delete($trimmed);
        }
    }

    private function resolveUniqueSlug(
        ?string $requestedSlug,
        string $firstName,
        ?string $middleName,
        string $lastName,
        ?int $ignoreId = null
    ): string {
        $raw = filled($requestedSlug)
            ? $requestedSlug
            : trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));

        $base = Str::slug($raw);
        if ($base === '') {
            $base = 'teacher';
        }
        $base = Str::limit($base, 170, '');

        $slug = $base;
        $suffix = 2;
        while (
            Teacher::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = Str::limit($base, 170, '').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function formatTeacher(Teacher $t): array
    {
        return [
            'id' => $t->id,
            'slug' => $t->slug,
            'firstName' => $t->first_name,
            'middleName' => $t->middle_name ?? '',
            'lastName' => $t->last_name,
            'fullName' => $t->full_name,
            'gender' => $t->gender ?? '',
            'phone' => $t->phone ?? '',
            'email' => $t->email ?? '',
            'address' => $t->address ?? '',
            'qualification' => $t->qualification ?? '',
            'specialization' => $t->specialization ?? '',
            'joiningDate' => $t->joining_date?->format('Y-m-d') ?? '',
            'profileImage' => $t->profileImageUrl(),
            'bio' => $t->bio ?? '',
            'isVisible' => (bool) $t->is_visible,
            'status' => $t->status ?? 'active',
        ];
    }
}
