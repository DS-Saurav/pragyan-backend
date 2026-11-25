<?php

namespace App\Services\Media;

use App\Models\Setting;

class HeroBackfillPlan
{
    /**
     * Read-only inventory and cutover manifest. It never creates a MediaAsset,
     * changes legacy JSON, moves a file, or turns on Media Library rendering.
     * A future approved importer must revalidate this manifest before copying
     * any selected source into private Media Library storage.
     */
    public function inspect(): array
    {
        $raw = Setting::get('website_hero_images');
        $entries = $raw === null || $raw === '' ? [] : json_decode($raw, true);
        $sourceChecksum = hash('sha256', (string) $raw);
        $report = [
            'mode' => 'dry-run',
            'source_checksum' => $sourceChecksum,
            'assets' => [],
            'attachments' => [],
            'errors' => [],
            'cutover' => [
                'ready' => false,
                'live_switch_allowed' => false,
                'source_preserved' => true,
                'manifest' => [
                    'source_checksum' => $sourceChecksum,
                    'copy_only' => true,
                    'legacy_source_removal_allowed' => false,
                    'rollback' => 'Use the no-photo fallback; never reactivate legacy public storage automatically.',
                ],
                'requirements' => [
                    'owner_approved_image_selection' => 'required',
                    'meaningful_alt_text' => 'required for every non-decorative image',
                    'consent_or_licence_evidence' => 'required for every selected image',
                    'media_library_publication' => 'required before rendering through /media/{id}',
                ],
                'blockers' => [],
            ],
        ];
        if (! is_array($entries) || ! array_is_list($entries)) {
            $report['errors'][] = 'The hero setting must be a JSON list.';
            $report['cutover']['blockers'][] = 'The legacy hero list is invalid and cannot be reconciled safely.';

            return $report;
        }
        $missingAltText = false;
        foreach ($entries as $order => $entry) {
            try {
                if (! is_array($entry) || ! is_string($entry['path'] ?? null) || ! is_string($entry['alt'] ?? '')) {
                    throw new \RuntimeException('Invalid hero entry.');
                }
                $bytes = app(MediaFiles::class)->legacyBytes($entry['path']);
                $info = @getimagesizefromstring($bytes);
                if ($info === false || ! in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
                    || $info[0] * $info[1] > MediaLibrary::MAX_PIXELS) {
                    throw new \RuntimeException('Unreadable hero image.');
                }
                $image = @imagecreatefromstring($bytes);
                if ($image === false) {
                    throw new \RuntimeException('Corrupt hero image.');
                }
                imagedestroy($image);
                $key = hash('sha256', $bytes);
                $report['assets'][$key] ??= [
                    'source_disk' => 'public',
                    'source_path' => $entry['path'],
                    'checksum' => $key,
                    'mime_type' => $info['mime'],
                    'width' => $info[0],
                    'height' => $info[1],
                    'owner_approval' => 'not_recorded',
                    'consent_or_licence_evidence' => 'not_recorded',
                ];
                $altText = trim($entry['alt']);
                $missingAltText = $missingAltText || $altText === '';
                $report['attachments'][] = ['source_key' => $key, 'source_disk' => 'public', 'source_path' => $entry['path'], 'placement' => 'hero', 'display_order' => $order,
                    'alt_text' => $altText, 'focal_x' => 50, 'focal_y' => 50];
            } catch (\Throwable) {
                $report['errors'][] = 'Hero entry '.$order.' is invalid, missing, corrupt or unsafe; source preserved.';
            }
        }
        if ($report['errors'] !== []) {
            $report['cutover']['blockers'][] = 'Every selected legacy source must pass file validation before a future import.';
        }
        if ($report['attachments'] === []) {
            $report['cutover']['blockers'][] = 'There are no valid legacy hero sources to prepare for cutover.';
        }
        if ($missingAltText) {
            $report['cutover']['blockers'][] = 'Meaningful alt text is missing for one or more legacy hero images.';
        }
        if ($report['attachments'] !== []) {
            $report['cutover']['blockers'][] = 'Owner approval and consent or licence evidence are not recorded for legacy hero sources.';
        }

        return $report;
    }
}
