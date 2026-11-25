<?php

use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Services\PublicPhotos\PublicPhotoSlots;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$actor = User::query()->where('email', 'admin@pragyan.edu.np')->firstOrFail();
if (! $actor->hasRole('admin')) {
    throw new RuntimeException('The named public-content admin account does not have the expected role.');
}

$firstPath = 'C:/Users/saure/Downloads/493274229_1503060951031033_8621804763274479512_n.jpg';
$secondPath = 'C:/Users/saure/Downloads/495887347_1513631469973981_777139939814264698_n.jpg';
foreach ([$firstPath, $secondPath] as $path) {
    if (! is_readable($path)) {
        throw new RuntimeException('An owner-supplied campus photograph is unavailable.');
    }
}

$second = MediaAsset::query()->where('checksum', hash_file('sha256', $secondPath))->firstOrFail();
if ($second->status !== 'published' || $second->archived_at !== null) {
    throw new RuntimeException('The previously imported campus photograph is not a live published asset.');
}

$media = app(MediaLibrary::class);
$first = MediaAsset::query()->where('checksum', hash_file('sha256', $firstPath))->first();
if ($first === null) {
    $first = $media->upload($actor, new UploadedFile($firstPath, basename($firstPath), 'image/jpeg', null, true), [
        'title' => 'Pragyan Pathashala aerial campus and courtyard',
        'category' => 'campus',
        'default_alt_text' => 'Aerial view of Pragyan Pathashala campus and courtyard in Birendranagar.',
    ]);
    $first = $media->confirmConsent($actor, $first);
    $first = $media->submit($actor, $first);
    $first = $media->approve($actor, $first);
    $first = $media->publish($actor, $first);
} elseif ($first->status !== 'published' || $first->archived_at !== null) {
    throw new RuntimeException('The matching first photograph is not a live published asset.');
}

if ($first->consent_state !== 'confirmed') {
    $first = $media->confirmConsent($actor, $first);
}
if ($second->consent_state !== 'confirmed') {
    $second = $media->confirmConsent($actor, $second);
}

$slots = app(PublicPhotoSlots::class);
$rights = 'Owner supplied both aerial campus photographs and confirmed in the project conversation that the school has permission to publish them, including people visible. No separate signed release was supplied. Original filenames are retained in the private Media Library.';
$assignments = [
    'home_hero' => [
        'asset' => $first,
        'alt_text' => 'Aerial view of Pragyan Pathashala campus and courtyard in Birendranagar.',
        'alt_text_ne' => 'वीरेन्द्रनगरस्थित विद्यालयका भवन र प्राङ्गणको हवाई दृश्य।',
        'focal_x' => 53,
        'focal_y' => 36,
    ],
    'home_learning' => [
        'asset' => $second,
        'alt_text' => 'Aerial view of Pragyan Pathashala campus buildings beside the road.',
        'alt_text_ne' => 'सडक छेउमा रहेका विद्यालयका भवनहरूको हवाई दृश्य।',
        'focal_x' => 50,
        'focal_y' => 45,
    ],
    'admissions_hero' => [
        'asset' => $second,
        'alt_text' => 'Aerial view of Pragyan Pathashala buildings and courtyard from the road.',
        'alt_text_ne' => 'सडकबाट देखिने विद्यालयका भवन र प्राङ्गणको हवाई दृश्य।',
        'focal_x' => 50,
        'focal_y' => 45,
    ],
    'contact_visit' => [
        'asset' => $second,
        'alt_text' => 'Roadside aerial view of Pragyan Pathashala campus in Birendranagar.',
        'alt_text_ne' => 'वीरेन्द्रनगरस्थित विद्यालय परिसरको सडकतर्फबाट लिइएको हवाई दृश्य।',
        'focal_x' => 50,
        'focal_y' => 56,
    ],
];

foreach ($assignments as $key => $assignment) {
    if (! $slots->assetIsPublic($assignment['asset'])) {
        throw new RuntimeException('A campus photograph failed the public media safety checks.');
    }
    $current = App\Models\PublicPhotoSlot::query()->where('slot_key', $key)->firstOrFail();
    if ($current->media_asset_id === $assignment['asset']->id
        && $current->alt_text === $assignment['alt_text']
        && $current->alt_text_ne === $assignment['alt_text_ne']) {
        continue;
    }
    $slots->save($actor, $key, [
        'media_asset_id' => $assignment['asset']->id,
        'alt_text' => $assignment['alt_text'],
        'alt_text_ne' => $assignment['alt_text_ne'],
        'caption' => '',
        'caption_ne' => '',
        'focal_x' => $assignment['focal_x'],
        'focal_y' => $assignment['focal_y'],
        'source_rights_note' => $rights.' Placement: '.$key.'.',
    ]);
}

echo json_encode([
    'first_asset_id' => $first->id,
    'second_asset_id' => $second->id,
    'assigned_slots' => array_keys($assignments),
    'unassigned_slots' => [],
], JSON_PRETTY_PRINT).PHP_EOL;
