<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaFiles
{
    public function originalPath(MediaAsset $asset): string
    {
        $this->check($asset->disk === 'media_originals' &&
            preg_match('/\Aoriginals\/[a-f0-9-]{36}\.(jpg|png|webp|gif)\z/D', $asset->original_path));

        return $asset->original_path;
    }

    public function publicPath(MediaAsset $asset): string
    {
        $this->check($asset->public_disk === 'media_derivatives' &&
            is_string($asset->public_path) && preg_match('/\Apublished\/[a-f0-9-]{36}\.jpg\z/D', $asset->public_path));

        return $asset->public_path;
    }

    public function legacyBytes(string $path): string
    {
        $this->check((bool) preg_match('/\Ahero\/[A-Za-z0-9_-]+\.(jpg|jpeg|png|webp|gif)\z/D', $path));
        $disk = Storage::disk('public');
        $root = realpath($disk->path('hero'));
        $resolved = realpath($disk->path($path));
        $this->check($root !== false && $resolved !== false &&
            str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $root).'/'));
        $bytes = $disk->get($path);
        $this->check(is_string($bytes) && $bytes !== '');

        return $bytes;
    }

    private function check(bool $valid): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['media' => 'The media file reference is unavailable or unsafe.']);
        }
    }
}
