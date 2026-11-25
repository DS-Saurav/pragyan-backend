<?php

namespace App\Http\Controllers;

use App\Models\GalleryAlbum;
use App\Services\Gallery\GalleryVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PublicGalleryController extends Controller
{
    public function __construct(private GalleryVisibility $galleryVisibility) {}

    public function index(): View
    {
        return view('frontend.gallery', [
            'albums' => $this->galleryVisibility->displayableQuery()
                ->with(['publishedRevision.cover.asset', 'publishedRevision.photos.asset'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(9)
                ->withQueryString(),
            'isArchive' => false,
        ]);
    }

    public function archive(): View
    {
        return view('frontend.gallery', [
            'albums' => $this->galleryVisibility->displayableQuery()
                ->with(['publishedRevision.cover.asset', 'publishedRevision.photos.asset'])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(24)
                ->withQueryString(),
            'isArchive' => true,
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $result = $this->galleryVisibility->findDisplayableSlug($slug);

        if ($result['redirect'] instanceof GalleryAlbum) {
            return redirect()->route('public.gallery.show', ['slug' => $result['redirect']->slug], 301);
        }

        abort_unless($result['album'] instanceof GalleryAlbum, 404);
        $album = $result['album']->load([
            'publishedRevision.cover.asset',
            'publishedRevision.photos.asset',
        ]);
        $revision = $album->publishedRevision;
        abort_unless($revision !== null, 404);

        $cover = $revision->cover;
        $coverUrl = $cover?->asset?->public_path !== null
            ? route('media.published', $cover->media_asset_id)
            : null;

        return view('frontend.gallery-show', [
            'album' => $album,
            'revision' => $revision,
            'cover' => $cover,
            'coverUrl' => $coverUrl,
        ]);
    }
}
