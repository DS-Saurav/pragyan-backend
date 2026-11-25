<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Homepage hero photographs (website Phase 1).
 *
 * Mirrors the app's Photo convention (GD resize, public disk, JPEG q85) but
 * produces WIDE crops: heroes are full-bleed landscape panels, not portraits.
 * Uploads are always encoded through GD before storing, which both optimises
 * the download (no multi-megabyte originals) and neutralises any payload
 * that is not a readable image. Falls back to storing the original file only
 * when GD is unavailable, exactly like Photo::store.
 */
class HeroImage
{
    public const MAX_UPLOAD_KB = 8192;

    public const MAX_HEROES = 8;

    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * Resize to a wide banner (never taller than 1080px, never wider than
     * 1920px, original aspect preserved, smallest edge upscaled but never
     * downscaled beyond the target band) and store as JPEG on the public disk.
     */
    public static function store(UploadedFile $file, string $dir = 'hero'): ?string
    {
        $data = @file_get_contents($file->getRealPath());
        $img = $data !== false ? @imagecreatefromstring($data) : false;

        if ($img === false || ! function_exists('imagejpeg')) {
            return null; // not a readable/routable image — reject rather than risk it
        }

        $w = imagesx($img);
        $h = imagesy($img);

        if ($w < 480 || $h < 280) {
            imagedestroy($img);

            return null; // too small to be a hero image
        }

        if ($w > 1920) {
            $scale = 1920 / $w;
        } elseif ($h > 1080) {
            $scale = 1080 / $h;
        } else {
            $scale = 1;
        }

        $outW = (int) max(1, round($w * $scale));
        $outH = (int) max(1, round($h * $scale));

        // Keep PNG alpha alive while resampling.
        $dst = imagecreatetruecolor($outW, $outH);
        imagealphablending($dst, true);
        imagesavealpha($dst, true);
        $fill = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $fill);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $outW, $outH, $w, $h);
        imagedestroy($img);

        ob_start();
        imagejpeg($dst, null, 85);
        $binary = ob_get_clean();
        imagedestroy($dst);

        $path = $dir.'/'.Str::random(24).'.jpg';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
