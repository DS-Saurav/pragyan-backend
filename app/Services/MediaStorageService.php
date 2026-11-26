<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStorageService
{
    /**
     * Resolve the active storage disk ('r2' when configured, otherwise 'public').
     */
    public function disk(): string
    {
        if (
            config('filesystems.disks.r2.bucket') &&
            config('filesystems.disks.r2.endpoint') &&
            config('filesystems.disks.r2.key') &&
            config('filesystems.disks.r2.secret')
        ) {
            return 'r2';
        }

        return 'public';
    }

    /**
     * Upload a file to the specified folder on the active disk.
     *
     * @return array{disk: string, path: string, mime_type: ?string, file_size: int|false}
     */
    public function upload(UploadedFile $file, string $folder): array
    {
        $disk = $this->disk();
        $path = $file->store(trim($folder, '/'), $disk);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException("Failed to store file on {$disk} disk.");
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    /**
     * Build a public URL for a stored file, using R2_URL when stored on R2.
     */
    public function url(?string $disk, ?string $path, ?string $fallbackUrl = null): ?string
    {
        if (! $path) {
            return null;
        }

        $diskName = $disk ?: 'public';

        if ($diskName === 'r2' && config('filesystems.disks.r2.url')) {
            return Storage::disk('r2')->url($path);
        }

        if ($fallbackUrl !== null) {
            return $fallbackUrl;
        }

        return Storage::disk($diskName)->url($path);
    }

    /**
     * Delete a stored file from its disk if it exists.
     */
    public function delete(?string $disk, ?string $path): void
    {
        if (! $path) {
            return;
        }

        $diskName = $disk ?: 'public';
        if (Storage::disk($diskName)->exists($path)) {
            Storage::disk($diskName)->delete($path);
        }
    }

    /**
     * Stream a stored file with cache headers.
     */
    public function stream(?string $disk, ?string $path): StreamedResponse
    {
        $diskName = $disk ?: 'public';
        $storage = Storage::disk($diskName);
        abort_unless($path && $storage->exists($path), 404);

        return $storage->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
