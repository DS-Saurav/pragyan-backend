<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Controller;
use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumRevision;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Setting;
use App\Models\User;
use App\Services\Gallery\GalleryPublisher;
use App\Services\Media\MediaLibrary;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GalleryAlbumController extends Controller
{
    public function __construct(
        private GalleryPublisher $galleryPublisher,
        private MediaLibrary $mediaLibrary,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorizeAny(['gallery.author', 'gallery.review', 'gallery.publish']);

        return response()->json([
            'albums' => GalleryAlbum::query()
                ->with(['creator', 'editor', 'publisher', 'revisions', 'publishedRevision', 'scheduledRevision'])
                ->orderByDesc('updated_at')
                ->paginate(30),
            'featuredAlbumId' => (int) Setting::get(GalleryPublisher::FEATURED_SETTING),
        ]);
    }

    public function create(): JsonResponse
    {
        Gate::authorize('gallery.author');

        return $this->formView(new GalleryAlbum([
            'publication_state' => 'draft',
        ]), new GalleryAlbumRevision([
            'workflow_state' => 'draft',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('gallery.author');
        $album = $this->galleryPublisher->createDraft($this->actor(), $this->albumData($this->validated($request)));

        return response()->json(["message" => 'Gallery album saved as a private draft. Add a cover and ordered photographs before submitting it for review.']);
    }

    public function edit(GalleryAlbum $galleryAlbum): JsonResponse
    {
        $this->authorizeAny(['gallery.author', 'gallery.review', 'gallery.publish']);
        $galleryAlbum->load([
            'revisions.cover.asset',
            'revisions.photos.asset',
            'publishedRevision.cover.asset',
            'publishedRevision.photos.asset',
            'scheduledRevision.cover.asset',
            'scheduledRevision.photos.asset',
            'creator',
            'editor',
            'publisher',
        ]);

        return $this->formView($galleryAlbum, $this->activeRevision($galleryAlbum));
    }

    public function update(Request $request, GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.author');
        $this->galleryPublisher->saveDraft($this->actor(), $galleryAlbum, $this->albumData($this->validated($request)));

        return response()->json(["message" => $galleryAlbum->publication_state === 'published'
                ? 'A private replacement revision was saved. The currently published album has not changed.'
                : 'Gallery draft saved.']);
    }

    public function preview(GalleryAlbum $galleryAlbum, GalleryAlbumRevision $revision): JsonResponse
    {
        $this->authorizeAny(['gallery.author', 'gallery.review', 'gallery.publish']);
        $this->ensureRevisionBelongsToAlbum($galleryAlbum, $revision);
        $revision->load(['cover.asset', 'photos.asset']);

        return response()->json([
            'album' => $galleryAlbum,
            'revision' => $revision,
            'cover' => $revision->cover,
            'photos' => $revision->photos,
        ]);
    }

    public function setCover(Request $request, GalleryAlbum $galleryAlbum, GalleryAlbumRevision $revision): JsonResponse
    {
        Gate::authorize('gallery.author');
        $this->ensureRevisionBelongsToAlbum($galleryAlbum, $revision);
        $data = $this->photoMetadata($request, true, 'cover_asset_id');
        $asset = MediaAsset::findOrFail($data['cover_asset_id']);
        unset($data['cover_asset_id']);
        $this->mediaLibrary->replaceGalleryAlbumRevisionCover($this->actor(), $revision, $asset, $data);

        return response()->json(["message" => 'Gallery cover photograph saved.']);
    }

    public function addPhoto(Request $request, GalleryAlbum $galleryAlbum, GalleryAlbumRevision $revision): JsonResponse
    {
        Gate::authorize('gallery.author');
        $this->ensureRevisionBelongsToAlbum($galleryAlbum, $revision);
        $data = $this->photoMetadata($request, true);
        $asset = MediaAsset::findOrFail($data['media_asset_id']);
        unset($data['media_asset_id']);
        $this->mediaLibrary->addGalleryAlbumRevisionPhoto($this->actor(), $revision, $asset, $data);

        return response()->json(["message" => 'Photograph added to the private Gallery revision.']);
    }

    public function updatePhoto(Request $request, GalleryAlbum $galleryAlbum, GalleryAlbumRevision $revision, MediaAttachment $photo): JsonResponse
    {
        Gate::authorize('gallery.author');
        $this->ensureRevisionBelongsToAlbum($galleryAlbum, $revision);
        $this->ensurePhotoBelongsToRevision($revision, $photo);
        $data = $this->photoMetadata($request, false);
        $assetId = $data['media_asset_id'] ?? null;
        unset($data['media_asset_id']);

        if ($assetId === null || (int) $assetId === $photo->media_asset_id) {
            $this->mediaLibrary->updateGalleryAlbumRevisionPhoto($this->actor(), $revision, $photo, $data);
        } else {
            $asset = MediaAsset::findOrFail($assetId);
            $this->mediaLibrary->replaceGalleryAlbumRevisionPhoto($this->actor(), $revision, $photo, $asset, $data);
        }

        return response()->json(["message" => 'Gallery photograph updated.']);
    }

    public function removePhoto(GalleryAlbum $galleryAlbum, GalleryAlbumRevision $revision, MediaAttachment $photo): JsonResponse
    {
        Gate::authorize('gallery.author');
        $this->ensureRevisionBelongsToAlbum($galleryAlbum, $revision);
        $this->ensurePhotoBelongsToRevision($revision, $photo);
        $this->mediaLibrary->removeGalleryAlbumRevisionPhoto($this->actor(), $revision, $photo);

        return response()->json(["message" => 'Photograph removed from the private Gallery revision.']);
    }

    public function reorderPhotos(Request $request, GalleryAlbum $galleryAlbum, GalleryAlbumRevision $revision): JsonResponse
    {
        Gate::authorize('gallery.author');
        $this->ensureRevisionBelongsToAlbum($galleryAlbum, $revision);
        $rawOrder = trim((string) $request->input('photo_order'));
        $photoIds = $rawOrder === '' ? [] : array_map('trim', explode(',', $rawOrder));
        $this->mediaLibrary->reorderGalleryAlbumRevisionPhotos($this->actor(), $revision, $photoIds);

        return response()->json(["message" => 'Gallery photograph order saved.']);
    }

    public function submit(GalleryAlbum $galleryAlbum, GalleryAlbumRevision $revision): JsonResponse
    {
        Gate::authorize('gallery.author');
        $this->ensureRevisionBelongsToAlbum($galleryAlbum, $revision);
        $this->galleryPublisher->submit($this->actor(), $galleryAlbum, $revision);

        return response()->json(["message" => 'Gallery revision submitted for review.']);
    }

    public function approve(GalleryAlbum $galleryAlbum, GalleryAlbumRevision $revision): JsonResponse
    {
        Gate::authorize('gallery.review');
        $this->ensureRevisionBelongsToAlbum($galleryAlbum, $revision);
        $this->galleryPublisher->approve($this->actor(), $galleryAlbum, $revision);

        return response()->json(["message" => 'Gallery revision approved for publication.']);
    }

    public function publish(Request $request, GalleryAlbum $galleryAlbum, GalleryAlbumRevision $revision): JsonResponse
    {
        Gate::authorize('gallery.publish');
        $this->ensureRevisionBelongsToAlbum($galleryAlbum, $revision);
        $data = $request->validate([
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ]);
        $scheduledFor = empty($data['scheduled_for'])
            ? null
            : CarbonImmutable::parse($data['scheduled_for'], GalleryPublisher::TIMEZONE);
        $this->galleryPublisher->publish($this->actor(), $galleryAlbum, $revision, $scheduledFor);

        return response()->json(["message" => $scheduledFor === null
            ? 'Gallery album published.'
            : 'Gallery album scheduled for publication.']);
    }

    public function withdraw(GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.publish');
        $this->galleryPublisher->withdraw($this->actor(), $galleryAlbum);

        return response()->json(["message" => 'Gallery album withdrawn from the public website.']);
    }

    public function archive(GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.publish');
        $this->galleryPublisher->archive($this->actor(), $galleryAlbum);

        return response()->json(["message" => 'Gallery album archived. Its editorial history has been retained.']);
    }

    public function restore(GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.publish');
        $this->galleryPublisher->restore($this->actor(), $galleryAlbum);

        return response()->json(["message" => 'Gallery album restored as a private draft.']);
    }

    public function feature(GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.publish');
        $this->galleryPublisher->setFeatured($this->actor(), $galleryAlbum);

        return response()->json(["message" => 'This is now the optional Gallery preview on the homepage.']);
    }

    public function unfeature(GalleryAlbum $galleryAlbum): JsonResponse
    {
        Gate::authorize('gallery.publish');
        $this->galleryPublisher->setFeatured($this->actor(), null);

        return response()->json(["message" => 'The homepage no longer has a featured Gallery preview.']);
    }

    private function formView(GalleryAlbum $album, GalleryAlbumRevision $revision): JsonResponse
    {
        $revision->loadMissing(['cover.asset', 'photos.asset']);

        return response()->json([
            'album' => $album,
            'revision' => $revision,
            'assets' => MediaAsset::query()
                ->where('status', 'published')
                ->whereNull('archived_at')
                ->where('public_disk', 'media_derivatives')
                ->whereNotNull('public_path')
                ->where('public_path', '!=', '')
                ->whereNull('consent_withdrawn_at')
                ->whereIn('consent_state', ['not_required', 'confirmed'])
                ->where(function ($query): void {
                    $query->whereNotNull('default_alt_text')->where('default_alt_text', '!=', '')
                        ->orWhere('is_decorative', true);
                })
                ->orderByDesc('published_at')
                ->get(),
            'states' => GalleryAlbum::PUBLICATION_STATES,
            'revisionStates' => GalleryAlbumRevision::WORKFLOW_STATES,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'summary' => ['required', 'string', 'max:500'],
            'album_date' => ['required', 'date_format:Y-m-d'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function albumData(array $data): array
    {
        return [
            'title' => $data['title'],
            'slug' => $data['slug'] ?? null,
            'summary' => $data['summary'],
            'album_date' => $data['album_date'],
            'category' => $data['category'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function photoMetadata(Request $request, bool $requiresAsset, string $assetField = 'media_asset_id'): array
    {
        $data = $request->validate([
            $assetField => [$requiresAsset ? 'required' : 'nullable', 'integer', Rule::exists('media_assets', 'id')],
            'caption' => ['nullable', 'string', 'max:10000'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'focal_x' => ['nullable', 'numeric', 'between:0,100'],
            'focal_y' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        foreach (['focal_x', 'focal_y'] as $coordinate) {
            if ($requiresAsset) {
                $data[$coordinate] ??= 50;
            } elseif ($data[$coordinate] === null) {
                unset($data[$coordinate]);
            }
        }

        return $data;
    }

    private function activeRevision(GalleryAlbum $album): GalleryAlbumRevision
    {
        $active = $album->revisions
            ->filter(fn (GalleryAlbumRevision $revision): bool => in_array($revision->workflow_state, ['draft', 'review', 'approved', 'scheduled'], true))
            ->sortByDesc('revision_number')
            ->first();

        if ($active instanceof GalleryAlbumRevision) {
            return $active;
        }

        return $album->publishedRevision ?? new GalleryAlbumRevision(['workflow_state' => 'draft']);
    }

    private function ensureRevisionBelongsToAlbum(GalleryAlbum $album, GalleryAlbumRevision $revision): void
    {
        abort_unless($revision->gallery_album_id === $album->id, 404);
    }

    private function ensurePhotoBelongsToRevision(GalleryAlbumRevision $revision, MediaAttachment $photo): void
    {
        abort_unless(
            $photo->attachable_type === 'gallery-album-revision'
            && $photo->attachable_id === $revision->id
            && $photo->placement === 'photo',
            404,
        );
    }

    /** @param array<int, string> $abilities */
    private function authorizeAny(array $abilities): void
    {
        foreach ($abilities as $ability) {
            if (Gate::allows($ability)) {
                return;
            }
        }

        abort(403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
