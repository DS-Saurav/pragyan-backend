<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\MediaFiles;
use App\Services\Media\MediaLibrary;
use App\Services\Media\MediaSchema;
use App\Services\Media\MediaUsage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MediaFoundationController extends Controller
{
    public function index(MediaSchema $schema, MediaUsage $usage): View
    {
        $this->authorizeLibraryAccess();
        $schema->ensureReady();

        $assets = MediaAsset::query()
            ->with(['uploader', 'reviewer', 'publisher', 'consentConfirmer', 'consentWithdrawer'])
            ->withCount('attachments')
            ->latest('updated_at')
            ->paginate(20);
        $publishedUses = [];
        $usageErrors = [];

        foreach ($assets as $asset) {
            try {
                $publishedUses[$asset->id] = $usage->publishedUses($asset);
            } catch (ValidationException $exception) {
                // A failed inspection is shown explicitly and remains fail-closed in
                // MediaLibrary when a lifecycle action is attempted.
                $publishedUses[$asset->id] = [];
                $usageErrors[$asset->id] = implode(' ', Arr::flatten($exception->errors()));
            }
        }

        return view('admin.media.index', [
            'assets' => $assets,
            'categories' => MediaLibrary::CATEGORIES,
            'publishedUses' => $publishedUses,
            'usageErrors' => $usageErrors,
            'canView' => Gate::allows('media.view'),
            'canUpload' => Gate::allows('media.upload'),
            'canReview' => Gate::allows('media.review'),
            'canPublish' => Gate::allows('media.publish'),
        ]);
    }

    public function status(MediaSchema $schema)
    {
        Gate::authorize('media.view');
        $schema->ensureReady();

        return response()->json(['message' => 'Media Library is ready.']);
    }

    public function original(int $id, MediaSchema $schema, MediaFiles $files)
    {
        Gate::authorize('media.view');
        $schema->ensureReady();
        $asset = MediaAsset::findOrFail($id);
        $path = $files->originalPath($asset);
        abort_unless(Storage::disk('media_originals')->exists($path), 404);

        return Storage::disk('media_originals')->response($path, $asset->storage_filename, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function published(int $id, MediaSchema $schema, MediaFiles $files)
    {
        // No implicit binding: missing/incomplete schema is checked before any media query.
        $schema->ensureReady();
        $asset = MediaAsset::whereKey($id)->where('status', 'published')->whereNull('archived_at')
            ->where('consent_state', '!=', 'withdrawn')->whereNull('consent_withdrawn_at')->firstOrFail();
        $path = $files->publicPath($asset);
        abort_unless(Storage::disk('media_derivatives')->exists($path), 404);

        return Storage::disk('media_derivatives')->response($path, null, [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function upload(Request $request, MediaLibrary $library): RedirectResponse
    {
        $request->validate(['image' => ['required', 'file']]);
        $library->upload($request->user(), $request->file('image'), $this->metadata($request));

        return $this->libraryRedirect('Image uploaded as a private draft.');
    }

    public function updateMetadata(Request $request, MediaAsset $asset, MediaLibrary $library): RedirectResponse
    {
        $library->updateMetadata($request->user(), $asset, $this->metadata($request));

        return $this->libraryRedirect('Media details updated.');
    }

    public function submit(Request $request, MediaAsset $asset, MediaLibrary $library): RedirectResponse
    {
        $library->submit($request->user(), $asset);

        return $this->libraryRedirect('Media submitted for review.');
    }

    public function approve(Request $request, MediaAsset $asset, MediaLibrary $library): RedirectResponse
    {
        $library->approve($request->user(), $asset);

        return $this->libraryRedirect('Media review recorded.');
    }

    public function publish(Request $request, MediaAsset $asset, MediaLibrary $library): RedirectResponse
    {
        $library->publish($request->user(), $asset);

        return $this->libraryRedirect('Public derivative published.');
    }

    public function draft(Request $request, MediaAsset $asset, MediaLibrary $library): RedirectResponse
    {
        $library->draft($request->user(), $asset);

        return $this->libraryRedirect('Media returned to draft.');
    }

    public function archive(Request $request, MediaAsset $asset, MediaLibrary $library): RedirectResponse
    {
        $library->archive($request->user(), $asset);

        return $this->libraryRedirect('Media archived.');
    }

    public function restore(Request $request, MediaAsset $asset, MediaLibrary $library): RedirectResponse
    {
        $library->restore($request->user(), $asset);

        return $this->libraryRedirect('Media restored as a draft.');
    }

    public function consent(Request $request, MediaAsset $asset, MediaLibrary $library): RedirectResponse
    {
        $state = $request->validate([
            'consent_state' => ['required', Rule::in(['not_required', 'confirmed', 'withdrawn'])],
        ])['consent_state'];

        match ($state) {
            'confirmed' => $library->confirmConsent($request->user(), $asset),
            'withdrawn' => $library->withdrawConsent($request->user(), $asset),
            default => $library->markConsentNotRequired($request->user(), $asset),
        };

        return $this->libraryRedirect(match ($state) {
            'confirmed' => 'Image consent confirmed.',
            'withdrawn' => 'Image consent withdrawn. Affected News and Gallery publications were safely withdrawn; public page and Team placements now hide the image.',
            default => 'Marked as not requiring image consent.',
        });
    }

    /** @return array<string, mixed> */
    private function metadata(Request $request): array
    {
        return [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'default_alt_text' => $request->input('default_alt_text'),
            'photographer_credit' => $request->input('photographer_credit'),
            'category' => $request->input('category'),
            'is_decorative' => $request->boolean('is_decorative'),
        ];
    }

    private function libraryRedirect(string $message): RedirectResponse
    {
        return redirect()->route('admin.media.index')->with('success', $message);
    }

    private function authorizeLibraryAccess(): void
    {
        foreach (['media.view', 'media.upload', 'media.review', 'media.publish'] as $ability) {
            if (Gate::allows($ability)) {
                return;
            }
        }

        abort(403);
    }
}
