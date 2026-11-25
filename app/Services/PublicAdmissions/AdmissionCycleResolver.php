<?php

namespace App\Services\PublicAdmissions;

use App\Models\AdmissionCycle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class AdmissionCycleResolver
{
    public const TIMEZONE = 'Asia/Kathmandu';

    public function current(?CarbonInterface $now = null): ?AdmissionCycle
    {
        $today = $this->today($now);

        $cycle = AdmissionCycle::query()
            ->with(['grades', 'steps', 'requirements', 'faqs'])
            ->where('publication_state', 'published')
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('display_starts_on')
                    ->orWhereDate('display_starts_on', '<=', $today);
            })
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('display_ends_on')
                    ->orWhereDate('display_ends_on', '>=', $today);
            })
            ->orderByRaw('CASE WHEN display_starts_on IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('display_starts_on')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        return $cycle !== null && $this->isDisplayable($cycle, $now) ? $cycle : null;
    }

    public function isDisplayable(AdmissionCycle $cycle, ?CarbonInterface $now = null): bool
    {
        if ($cycle->publication_state !== 'published') {
            return false;
        }

        $today = $this->today($now);
        $startsOn = $this->dateValue($cycle->display_starts_on);
        $endsOn = $this->dateValue($cycle->display_ends_on);

        return ($startsOn === null || $startsOn <= $today)
            && ($endsOn === null || $endsOn >= $today);
    }

    /**
     * Call this inside the same transaction that persists a published cycle.
     */
    public function ensureNoPublishedWindowConflict(AdmissionCycle $candidate): void
    {
        if ($candidate->publication_state !== 'published') {
            return;
        }

        $existingCycles = AdmissionCycle::query()
            ->where('publication_state', 'published')
            ->when($candidate->exists, function (Builder $query) use ($candidate): void {
                $query->where('id', '!=', $candidate->getKey());
            })
            ->lockForUpdate()
            ->get();

        $candidateStartsOn = $this->dateValue($candidate->display_starts_on);
        $candidateEndsOn = $this->dateValue($candidate->display_ends_on);

        foreach ($existingCycles as $existingCycle) {
            if ($this->windowsOverlap(
                $candidateStartsOn,
                $candidateEndsOn,
                $this->dateValue($existingCycle->display_starts_on),
                $this->dateValue($existingCycle->display_ends_on),
            )) {
                throw ValidationException::withMessages([
                    'display_starts_on' => 'The published display window overlaps another public admissions cycle.',
                ]);
            }
        }
    }

    private function today(?CarbonInterface $now = null): string
    {
        return ($now ?? CarbonImmutable::now(self::TIMEZONE))
            ->setTimezone(self::TIMEZONE)
            ->toDateString();
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof CarbonInterface
            ? $value->setTimezone(self::TIMEZONE)->toDateString()
            : CarbonImmutable::parse((string) $value, self::TIMEZONE)->toDateString();
    }

    private function windowsOverlap(?string $startsOnA, ?string $endsOnA, ?string $startsOnB, ?string $endsOnB): bool
    {
        return ($endsOnA === null || $startsOnB === null || $endsOnA >= $startsOnB)
            && ($endsOnB === null || $startsOnA === null || $endsOnB >= $startsOnA);
    }
}
