<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HeroSlideController extends Controller
{
    public function __construct(
        private MediaStorageService $storage,
    ) {}

    public function index(): JsonResponse
    {
        $slides = HeroSlide::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HeroSlide $slide): array => $slide->toApiArray());

        return response()->json(['slides' => $slides]);
    }

    public function store(Request $request): JsonResponse
    {
        $rawFile = $request->files->get('image');
        if ($rawFile && ! $rawFile->isValid() && in_array($rawFile->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $maxUpload = ini_get('upload_max_filesize') ?: '2M';

            return response()->json([
                'message' => "Image is larger than PHP's upload_max_filesize ({$maxUpload}). Choose a smaller image or start PHP with -d upload_max_filesize=10M.",
            ], 422);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,avif|max:10240',
            'alt_text' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            $stored = $this->storage->upload($request->file('image'), 'hero-slides');
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to upload image to '.$this->storage->disk().' storage: '.$e->getMessage(),
            ], 500);
        }

        $nextOrder = (HeroSlide::max('sort_order') ?? 0) + 1;

        $slide = HeroSlide::create([
            'disk' => $stored['disk'],
            'image_path' => $stored['path'],
            'alt_text' => $validated['alt_text'] ?? 'Pragyan Paathshala campus',
            'mime_type' => $stored['mime_type'],
            'file_size' => $stored['file_size'] ?: null,
            'sort_order' => $nextOrder,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ]);

        return response()->json(['slide' => $slide->toApiArray()], 201);
    }

    public function update(Request $request, HeroSlide $heroSlide): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:10240',
            'alt_text' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            try {
                $stored = $this->storage->upload($request->file('image'), 'hero-slides');
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Failed to upload image to '.$this->storage->disk().' storage: '.$e->getMessage(),
                ], 500);
            }

            $this->storage->delete($heroSlide->disk, $heroSlide->image_path);
            $heroSlide->disk = $stored['disk'];
            $heroSlide->image_path = $stored['path'];
            $heroSlide->mime_type = $stored['mime_type'];
            $heroSlide->file_size = $stored['file_size'] ?: null;
        }

        if (array_key_exists('alt_text', $validated)) {
            $heroSlide->alt_text = $validated['alt_text'] ?? '';
        }
        if (array_key_exists('is_active', $validated)) {
            $heroSlide->is_active = (bool) $validated['is_active'];
        }

        $heroSlide->save();

        return response()->json(['slide' => $heroSlide->toApiArray()]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:hero_slides,id',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            HeroSlide::whereKey($id)->update(['sort_order' => $index + 1]);
        }

        return $this->index();
    }

    public function destroy(HeroSlide $heroSlide): JsonResponse
    {
        $this->storage->delete($heroSlide->disk, $heroSlide->image_path);
        $heroSlide->delete();

        return response()->json(['deleted' => true]);
    }

    public function image(HeroSlide $heroSlide): StreamedResponse
    {
        return $this->storage->stream($heroSlide->disk, $heroSlide->image_path);
    }
}
