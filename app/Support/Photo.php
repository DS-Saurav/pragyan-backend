<?php

namespace App\Support;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Photo
{
    /** Crop to a square, resize, save as JPEG on the public disk. Falls back to the original if GD is missing. */
    public static function store(UploadedFile $file, string $dir = 'staff', int $size = 600): string
    {
        $data = @file_get_contents($file->getRealPath());

        if ($data !== false && function_exists('imagecreatefromstring') && ($img = @imagecreatefromstring($data))) {
            // phone photos: respect the camera's rotation
            if (function_exists('exif_read_data') && in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
                $exif = @exif_read_data($file->getRealPath());
                $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
                if ($angle && ($rotated = @imagerotate($img, $angle, 0))) {
                    $img = $rotated;
                }
            }

            $w = imagesx($img);
            $h = imagesy($img);
            $side = min($w, $h);
            $x = intdiv($w - $side, 2);
            $y = (int) (($h - $side) * 0.2); // portraits: keep the top, where the face usually is
            $out = min($size, $side);

            $dst = imagecreatetruecolor($out, $out);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $img, 0, 0, $x, $y, $out, $out, $side, $side);

            ob_start();
            imagejpeg($dst, null, 85);
            $binary = ob_get_clean();

            $path = $dir.'/'.Str::random(24).'.jpg';
            Storage::disk('public')->put($path, $binary);

            return $path;
        }

        return $file->store($dir, 'public');
    }

    public static function url(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }

    /** Delete the file only when no user or staff record still uses it. */
    public static function deleteIfUnused(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (User::where('photo', $path)->exists() || StaffMember::where('photo', $path)->exists()) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
