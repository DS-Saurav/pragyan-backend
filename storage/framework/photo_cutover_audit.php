<?php

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = App\Models\User::query()->where('email', 'admin@pragyan.edu.np')->first();
$assets = App\Models\MediaAsset::query()
    ->get(['id', 'original_filename', 'status', 'consent_state', 'default_alt_text', 'checksum', 'width', 'height'])
    ->map(fn ($asset) => $asset->toArray());

echo json_encode([
    'admin' => $admin ? ['id' => $admin->id, 'is_admin' => $admin->hasRole('admin')] : null,
    'assets' => $assets,
    'slots_table_exists' => Illuminate\Support\Facades\Schema::hasTable('public_photo_slots'),
    'pages' => App\Models\PublicPage::query()->with(['publishedRevision.featureImage', 'revisions'])->get()->map(fn ($page) => [
        'id' => $page->id,
        'type' => $page->type,
        'state' => $page->publication_state,
        'published_revision_id' => $page->published_revision_id,
        'feature_asset_id' => $page->publishedRevision?->featureImage?->media_asset_id,
        'revision_states' => $page->revisions->pluck('workflow_state')->all(),
    ]),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
