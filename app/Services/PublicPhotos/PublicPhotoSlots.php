<?php

namespace App\Services\PublicPhotos;

use App\Models\MediaAsset;
use App\Models\PublicPhotoSlot;
use App\Models\User;
use App\Services\Media\MediaFiles;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicPhotoSlots
{
    /** @var array<string, string> */
    public const LABELS = [
        'home_hero' => 'Homepage lead photograph',
        'home_learning' => 'Homepage learning photograph',
        'admissions_hero' => 'Admissions photograph',
        'contact_visit' => 'Contact and visit photograph',
    ];

    /**
     * Return only public display fields. Rights evidence stays in the staff editor.
     * Nepali pages omit the image until its placement has Nepali alternative text.
     *
     * @return array{url: string, alt: string, caption: string, focal_x: string, focal_y: string, width: int, height: int, asset_id: int}|null
     */
    public function resolve(string $slot, string $locale = 'en'): ?array
    {
        if (! array_key_exists($slot, self::LABELS) || ! in_array($locale, ['en', 'ne'], true)) {
            return null;
        }

        try {
            $record = PublicPhotoSlot::query()->with('asset')->where('slot_key', $slot)->first();
        } catch (QueryException) {
            // During an additive deployment the old text layout remains usable.
            return null;
        }

        $asset = $record?->asset;
        $alt = trim((string) ($locale === 'ne' ? $record?->alt_text_ne : $record?->alt_text));
        if ($asset === null || $alt === '' || ! $this->assetIsPublic($asset)) {
            return null;
        }

        return [
            'url' => route('media.published', ['id' => $asset->getKey()]),
            'alt' => $alt,
            'caption' => trim((string) $record->caption),
            'focal_x' => (string) $record->focal_x,
            'focal_y' => (string) $record->focal_y,
            'width' => (int) $asset->width,
            'height' => (int) $asset->height,
            'asset_id' => (int) $asset->getKey(),
        ];
    }

    /**
     * Assign one already-published Media Library asset to one fixed public slot.
     * Every edit leaves an attribution trail, including removal and replacement.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(User $actor, string $slot, array $data): PublicPhotoSlot
    {
        Gate::forUser($actor)->authorize('public_homepage.manage');
        abort_unless($actor->hasRole('admin'), 403);
        abort_unless(array_key_exists($slot, self::LABELS), 404);

        $validated = Validator::make($data, [
            'media_asset_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')],
            'alt_text' => ['required_with:media_asset_id', 'nullable', 'string', 'max:255'],
            'alt_text_ne' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'focal_x' => ['nullable', 'numeric', 'between:0,100'],
            'focal_y' => ['nullable', 'numeric', 'between:0,100'],
            'source_rights_note' => ['required_with:media_asset_id', 'nullable', 'string', 'max:2000'],
        ])->validate();

        $assetId = isset($validated['media_asset_id']) ? (int) $validated['media_asset_id'] : null;
        $alt = trim((string) ($validated['alt_text'] ?? ''));
        $rights = trim((string) ($validated['source_rights_note'] ?? ''));
        if ($assetId !== null && ($alt === '' || $rights === '')) {
            throw ValidationException::withMessages([
                'media_asset_id' => 'A meaningful English description and private source/rights note are required.',
            ]);
        }

        return DB::transaction(function () use ($actor, $slot, $assetId, $validated, $alt, $rights): PublicPhotoSlot {
            $asset = $assetId === null ? null : MediaAsset::query()->whereKey($assetId)->lockForUpdate()->firstOrFail();
            if ($asset !== null && ! $this->assetIsPublic($asset)) {
                throw ValidationException::withMessages(['media_asset_id' => 'Select an active, published Media Library image with a safe derivative and current consent.']);
            }

            $record = PublicPhotoSlot::query()->where('slot_key', $slot)->lockForUpdate()->firstOrFail();
            $oldAssetId = $record->media_asset_id;
            $record->forceFill([
                'media_asset_id' => $assetId,
                'alt_text' => $asset === null ? null : $alt,
                'alt_text_ne' => $asset === null ? null : trim((string) ($validated['alt_text_ne'] ?? '')),
                'caption' => $asset === null ? null : trim((string) ($validated['caption'] ?? '')),
                'focal_x' => $asset === null ? 50 : ($validated['focal_x'] ?? 50),
                'focal_y' => $asset === null ? 50 : ($validated['focal_y'] ?? 50),
                'source_rights_note' => $asset === null ? null : $rights,
                'approved_by' => $asset === null ? null : $actor->getKey(),
                'approved_at' => $asset === null ? null : now(),
            ])->save();

            $record->events()->create([
                'previous_media_asset_id' => $oldAssetId,
                'media_asset_id' => $assetId,
                'actor_id' => $actor->getKey(),
                'action' => $assetId === null ? 'cleared' : ($oldAssetId === $assetId ? 'updated' : 'selected'),
                'source_rights_note' => $assetId === null ? null : $rights,
                'created_at' => now(),
            ]);

            return $record->refresh();
        });
    }

    public function assetIsPublic(MediaAsset $asset): bool
    {
        if ($asset->status !== 'published' || $asset->archived_at !== null
            || ! in_array($asset->consent_state, ['not_required', 'confirmed'], true)
            || $asset->consent_withdrawn_at !== null
            || trim((string) $asset->default_alt_text) === '') {
            return false;
        }

        try {
            $path = app(MediaFiles::class)->publicPath($asset);
        } catch (ValidationException) {
            return false;
        }

        return Storage::disk('media_derivatives')->exists($path);
    }
}
