<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Controller;
use App\Models\AdmissionCycle;
use App\Services\PublicAdmissions\AdmissionCycleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicAdmissionCycleController extends Controller
{
    public function __construct(private AdmissionCycleResolver $admissionCycleResolver) {}

    public function index(): JsonResponse
    {
        $this->authorizeContentManagement();

        return response()->json([
            'cycles' => AdmissionCycle::query()
                ->with(['creator', 'editor', 'publisher'])
                ->orderByDesc('updated_at')
                ->get(),
        ]);
    }

    public function create(): JsonResponse
    {
        $this->authorizeContentManagement();

        return $this->formView(new AdmissionCycle([
            'public_status' => 'contact_us',
            'publication_state' => 'draft',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeContentManagement();
        $validated = $this->validated($request);

        $cycle = DB::transaction(function () use ($validated): AdmissionCycle {
            $cycle = new AdmissionCycle($this->cycleAttributes($validated));
            $cycle->publication_state = 'draft';
            $cycle->created_by = auth()->id();
            $cycle->updated_by = auth()->id();
            $cycle->save();

            $this->synchronizeChildren($cycle, $validated);

            return $cycle;
        });

        return response()->json(["message" => 'Public admissions cycle saved as a draft. Publish it separately when it is approved.']);
    }

    public function edit(AdmissionCycle $admissionCycle): JsonResponse
    {
        $this->authorizeContentManagement();

        return $this->formView($admissionCycle->load([
            'grades',
            'steps',
            'requirements',
            'faqs',
            'creator',
            'editor',
            'publisher',
        ]));
    }

    public function update(Request $request, AdmissionCycle $admissionCycle): JsonResponse
    {
        $this->authorizeContentManagement();
        $validated = $this->validated($request);

        DB::transaction(function () use ($admissionCycle, $validated): void {
            $cycle = AdmissionCycle::query()->lockForUpdate()->findOrFail($admissionCycle->getKey());
            $cycle->fill($this->cycleAttributes($validated));
            $cycle->updated_by = auth()->id();
            $this->admissionCycleResolver->ensureNoPublishedWindowConflict($cycle);
            $cycle->save();

            $this->synchronizeChildren($cycle, $validated);
        });

        return response()->json(["message" => 'Public admissions content updated.']);
    }

    public function publish(AdmissionCycle $admissionCycle): JsonResponse
    {
        $this->authorizeContentManagement();

        DB::transaction(function () use ($admissionCycle): void {
            $cycle = AdmissionCycle::query()->lockForUpdate()->findOrFail($admissionCycle->getKey());

            if (! in_array($cycle->publication_state, ['draft', 'withdrawn'], true)) {
                throw ValidationException::withMessages([
                    'publication_state' => 'Only draft or withdrawn cycles can be published.',
                ]);
            }

            $cycle->publication_state = 'published';
            $cycle->published_at ??= CarbonImmutable::now(AdmissionCycleResolver::TIMEZONE);
            $cycle->published_by = auth()->id();
            $cycle->updated_by = auth()->id();
            $this->admissionCycleResolver->ensureNoPublishedWindowConflict($cycle);
            $cycle->save();
        });

        return response()->json(["message" => 'Public admissions cycle published.']);
    }

    public function withdraw(AdmissionCycle $admissionCycle): JsonResponse
    {
        return $this->transition($admissionCycle, 'withdrawn', 'Public admissions cycle withdrawn from the website.');
    }

    public function archive(AdmissionCycle $admissionCycle): JsonResponse
    {
        return $this->transition($admissionCycle, 'archived', 'Public admissions cycle archived.');
    }

    private function formView(AdmissionCycle $cycle): JsonResponse
    {
        return response()->json([
            'cycle' => $cycle,
            'statuses' => AdmissionCycle::PUBLIC_STATUSES,
            'states' => AdmissionCycle::PUBLICATION_STATES,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'public_status' => ['required', Rule::in(array_keys(AdmissionCycle::PUBLIC_STATUSES))],
            'public_intro' => ['nullable', 'string', 'max:2000'],
            'display_starts_on' => ['nullable', 'date'],
            'display_ends_on' => ['nullable', 'date', 'after_or_equal:display_starts_on'],
            'public_phone' => ['nullable', 'string', 'max:50'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'primary_action_label' => ['nullable', 'string', 'max:60'],
            'primary_action_url' => [
                'nullable',
                'string',
                'max:255',
                static function ($attribute, $value, $fail): void {
                    if ($value !== null && $value !== '' && ! AdmissionCycle::isValidPublicActionUrl((string) $value)) {
                        $fail('The primary action destination must be a site path or an http(s)/mailto link.');
                    }
                },
            ],
            'fee_summary' => ['nullable', 'string', 'max:4000'],
            'grades' => ['nullable', 'array', 'max:20'],
            'grades.*' => ['array'],
            'grades.*.grade' => ['nullable', 'string', 'max:120'],
            'grades.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'steps' => ['nullable', 'array', 'max:20'],
            'steps.*' => ['array'],
            'steps.*.title' => ['nullable', 'string', 'max:160'],
            'steps.*.body' => ['nullable', 'string', 'max:2000'],
            'steps.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'requirements' => ['nullable', 'array', 'max:30'],
            'requirements.*' => ['array'],
            'requirements.*.label' => ['nullable', 'string', 'max:160'],
            'requirements.*.detail' => ['nullable', 'string', 'max:2000'],
            'requirements.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'faqs' => ['nullable', 'array', 'max:20'],
            'faqs.*' => ['array'],
            'faqs.*.question' => ['nullable', 'string', 'max:255'],
            'faqs.*.answer' => ['nullable', 'string', 'max:4000'],
            'faqs.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function cycleAttributes(array $validated): array
    {
        return [
            'label' => trim($validated['label']),
            'public_status' => $validated['public_status'],
            'public_intro' => $this->nullableString($validated['public_intro'] ?? null),
            'display_starts_on' => $validated['display_starts_on'] ?? null,
            'display_ends_on' => $validated['display_ends_on'] ?? null,
            'public_phone' => $this->nullableString($validated['public_phone'] ?? null),
            'public_email' => $this->nullableString($validated['public_email'] ?? null),
            'primary_action_label' => $this->nullableString($validated['primary_action_label'] ?? null),
            'primary_action_url' => $this->nullableString($validated['primary_action_url'] ?? null),
            'fee_summary' => $this->nullableString($validated['fee_summary'] ?? null),
        ];
    }

    /** @param array<string, mixed> $validated */
    private function synchronizeChildren(AdmissionCycle $cycle, array $validated): void
    {
        $cycle->grades()->delete();
        foreach ($this->orderedRows($validated['grades'] ?? [], ['grade']) as $position => $row) {
            $cycle->grades()->create([
                'grade' => trim((string) $row['grade']),
                'sort_order' => $position + 1,
            ]);
        }

        $cycle->steps()->delete();
        foreach ($this->orderedRows($validated['steps'] ?? [], ['title']) as $position => $row) {
            $cycle->steps()->create([
                'title' => trim((string) $row['title']),
                'body' => $this->nullableString($row['body'] ?? null),
                'sort_order' => $position + 1,
            ]);
        }

        $cycle->requirements()->delete();
        foreach ($this->orderedRows($validated['requirements'] ?? [], ['label']) as $position => $row) {
            $cycle->requirements()->create([
                'label' => trim((string) $row['label']),
                'detail' => $this->nullableString($row['detail'] ?? null),
                'sort_order' => $position + 1,
            ]);
        }

        $cycle->faqs()->delete();
        foreach ($this->orderedRows($validated['faqs'] ?? [], ['question', 'answer']) as $position => $row) {
            $cycle->faqs()->create([
                'question' => trim((string) $row['question']),
                'answer' => trim((string) $row['answer']),
                'sort_order' => $position + 1,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $requiredKeys
     * @return array<int, array<string, mixed>>
     */
    private function orderedRows(array $rows, array $requiredKeys): array
    {
        $ordered = [];

        foreach ($rows as $inputIndex => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($requiredKeys as $requiredKey) {
                if (trim((string) ($row[$requiredKey] ?? '')) === '') {
                    continue 2;
                }
            }

            $ordered[] = ['row' => $row, 'input_index' => $inputIndex];
        }

        usort($ordered, function (array $left, array $right): int {
            $leftOrder = (int) ($left['row']['sort_order'] ?? 0);
            $rightOrder = (int) ($right['row']['sort_order'] ?? 0);

            return $leftOrder === $rightOrder
                ? $left['input_index'] <=> $right['input_index']
                : $leftOrder <=> $rightOrder;
        });

        return array_column($ordered, 'row');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function transition(AdmissionCycle $admissionCycle, string $state, string $message): JsonResponse
    {
        $this->authorizeContentManagement();

        DB::transaction(function () use ($admissionCycle, $state): void {
            $cycle = AdmissionCycle::query()->lockForUpdate()->findOrFail($admissionCycle->getKey());

            if ($state === 'withdrawn' && $cycle->publication_state !== 'published') {
                throw ValidationException::withMessages([
                    'publication_state' => 'Only a published cycle can be withdrawn.',
                ]);
            }

            if ($state === 'archived' && $cycle->publication_state === 'archived') {
                throw ValidationException::withMessages([
                    'publication_state' => 'This cycle is already archived.',
                ]);
            }

            $cycle->publication_state = $state;
            $cycle->updated_by = auth()->id();
            $cycle->save();
        });

        return response()->json(["message" => $message]);
    }

    private function authorizeContentManagement(): void
    {
        Gate::authorize('public_admissions.manage');
    }
}
