<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\PublicPhotoSlot;
use App\Services\PublicPhotos\PublicPhotoSlots;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PublicPhotoSlotController extends Controller
{
    public function index(PublicPhotoSlots $photos): View
    {
        Gate::authorize('public_homepage.manage');
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $assets = MediaAsset::query()
            ->where('status', 'published')
            ->whereNull('archived_at')
            ->whereNull('consent_withdrawn_at')
            ->orderByDesc('published_at')
            ->get()
            ->filter(fn (MediaAsset $asset): bool => $photos->assetIsPublic($asset));

        return view('admin.public-photos.index', [
            'labels' => PublicPhotoSlots::LABELS,
            'slots' => PublicPhotoSlot::query()->with('asset')->get()->keyBy('slot_key'),
            'assets' => $assets,
            'previews' => collect(array_keys(PublicPhotoSlots::LABELS))
                ->mapWithKeys(fn (string $slot): array => [$slot => $photos->resolve($slot)]),
        ]);
    }

    public function update(Request $request, string $slot, PublicPhotoSlots $photos): RedirectResponse
    {
        $photos->save($request->user(), $slot, $request->all());

        return redirect()->route('admin.public-photos.index')
            ->with('success', 'Public photograph selection saved.');
    }
}
