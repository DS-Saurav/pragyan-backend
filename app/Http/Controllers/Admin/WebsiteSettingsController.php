<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\HeroImage;
use App\Support\SiteIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Website & homepage content management (public-site Phase 1).
 *
 * Public homepage content (hero images, headline, intro, CTAs, rotation,
 * statistics, introduction and highlights) intentionally has its own
 * admin-only public_homepage.manage boundary. It shares the existing
 * Setting::set() persistence shape without inheriting general settings access.
 */
class WebsiteSettingsController extends Controller
{
    /** Allowed auto-rotation intervals in seconds (0 = static hero). */
    public const ROTATIONS = [0, 5, 8, 12, 15, 20, 30];

    public function index()
    {
        $this->authorizeHomepageManagement();

        return view('admin.settings.website', [
            'settings' => SiteIdentity::all(),
            'rotations' => self::ROTATIONS,
            'heroes' => self::heroes(),
            'stats' => self::stats(),
            'highlights' => self::highlights(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeHomepageManagement();
        $validated = $request->validate(self::rules($request));

        // Plain, individually validated keys first.
        foreach ([
            'website_hero_headline', 'website_hero_intro',
            'website_cta_primary_text', 'website_cta_primary_url',
            'website_cta_secondary_text', 'website_cta_secondary_url',
            'website_home_intro',
        ] as $key) {
            Setting::set($key, trim((string) ($validated[$key] ?? '')));
        }

        // Rotation must be one of the known intervals.
        $rotation = (int) $request->input('website_hero_rotation', 0);
        Setting::set('website_hero_rotation', in_array($rotation, self::ROTATIONS, true) ? (string) $rotation : '0');

        // ALT text per uploaded hero image; keyed by the stored path so
        // reordering never mismatches a caption with its picture. Direct array
        // access (not dot notation) because stored paths contain dots.
        //
        // Saving text/settings NEVER rewrites images, ordering or alt text:
        // alt values are only replaced when the request explicitly provides
        // one for a stored path (the alt inputs on the details form); keys
        // that are absent keep their stored value untouched.
        $alts = (array) $request->input('alts', []);
        $current = array_map(function (array $hero) use ($alts) {
            if (array_key_exists($hero['path'], $alts)) {
                $hero['alt'] = trim((string) $alts[$hero['path']]);
            }

            return $hero;
        }, self::heroes());
        Setting::set('website_hero_images', json_encode($current));

        // Administrator-entered statistics (exactly the 4 editor rows).
        $stats = [];
        foreach ($request->input('stat', []) as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($value !== '' && $label !== '' && count($stats) < 4) {
                $stats[] = ['value' => $value, 'label' => $label];
            }
        }
        Setting::set('website_stats', json_encode($stats));

        // Learning highlights: up to 6 one-line entries from the textarea.
        $highlights = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('website_home_highlights', '')) as $line) {
            $line = trim($line);
            if ($line !== '' && count($highlights) < 6) {
                $highlights[] = $line;
            }
        }
        Setting::set('website_home_highlights', json_encode($highlights));

        return redirect()->route('admin.settings.website.index')
            ->with('success', 'Website content updated. The homepage reflects it immediately.');
    }

    public function uploadImages(Request $request)
    {
        $this->authorizeHomepageManagement();
        // Image validation lives in its OWN named error bag ("upload") so it
        // is surfaced exclusively inside the upload form's error area — it can
        // never be mistaken for a failure of the separate Save details action.
        $validator = Validator::make($request->all(), [
            'hero_files' => 'required|array|min:1|max:4',
            'hero_files.*' => sprintf(
                'file|image|mimes:jpeg,png,webp,gif|max:%d',
                HeroImage::MAX_UPLOAD_KB
            ),
        ], [], ['hero_files' => 'hero files']);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'upload');
        }

        $current = self::heroes();
        $appended = 0;

        foreach ($request->file('hero_files', []) as $file) {
            if (count($current) >= HeroImage::MAX_HEROES) {
                break;
            }

            $path = HeroImage::store($file);
            if ($path === null) {
                continue; // unreadable or too small; validation only passes real images
            }

            $current[] = ['path' => $path, 'alt' => ''];
            $appended++;
        }

        if ($appended === 0) {
            // Validation passed the files' semantics but GD could not process
            // any of them — reported in the upload bag as well, never globally.
            return back()->withErrors([
                'processing' => 'No hero image could be processed. Use a photograph at least 480×280 px.',
            ], 'upload');
        }

        // Uploading touches ONLY the hero image list — every other website
        // setting (headline, intro, CTAs, rotation, stats, highlights) is left
        // exactly as saved.
        Setting::set('website_hero_images', json_encode($current));

        return redirect()->route('admin.settings.website.index')
            ->with('success', $appended.' hero '.($appended === 1 ? 'image' : 'images').' uploaded. Add alt text below, then save details.');
    }

    public function imageAction(Request $request)
    {
        $this->authorizeHomepageManagement();
        // Same named error bag as uploads: hero-image-list errors never leak
        // into a position that reads like a Save details failure.
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:move-up,move-down,remove,delete',
            'index' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'upload');
        }

        $validated = $validator->validated();

        $heroes = self::heroes();
        $index = (int) $validated['index'];

        if (! array_key_exists($index, $heroes)) {
            return back();
        }

        $removedPath = null;

        if ($validated['action'] === 'move-up' && $index > 0) {
            [$heroes[$index - 1], $heroes[$index]] = [$heroes[$index], $heroes[$index - 1]];
        } elseif ($validated['action'] === 'move-down' && $index < count($heroes) - 1) {
            [$heroes[$index + 1], $heroes[$index]] = [$heroes[$index], $heroes[$index + 1]];
        } elseif ($validated['action'] === 'remove' || $validated['action'] === 'delete') {
            $removedPath = $heroes[$index]['path'];
            unset($heroes[$index]);
            $heroes = array_values($heroes);
        }

        Setting::set('website_hero_images', json_encode(array_values($heroes)));

        if ($removedPath !== null) {
            Storage::disk('public')->delete($removedPath);
        }

        return redirect()->route('admin.settings.website.index')
            ->with('success', match ($validated['action']) {
                'move-up', 'move-down' => 'Hero image order updated.',
                default => 'Hero image removed.',
            });
    }

    /** Validation rules reused by the details form (headline/intro/CTAs/stats). */
    public function rules(Request $request): array
    {
        // CTA destinations must be site paths or plain links. A closure (not
        // the `regex:` rule) because pipe-separated rule parsing mangles
        // patterns that contain alternation.
        $ctaUrl = ['nullable', 'string', 'max:255', static function ($attribute, $value, $fail) {
            if ($value !== null && $value !== '' && ! preg_match('#^(/|https?://|mailto:)#i', (string) $value)) {
                $fail('The destination must be a site path (starting with /) or an http(s)/mailto link.');
            }
        }];

        return [
            'website_hero_headline' => 'nullable|string|max:160',
            'website_hero_intro' => 'nullable|string|max:400',
            'website_cta_primary_text' => 'nullable|string|max:60',
            'website_cta_primary_url' => $ctaUrl,
            'website_cta_secondary_text' => 'nullable|string|max:60',
            'website_cta_secondary_url' => $ctaUrl,
            'website_hero_rotation' => 'nullable|integer',
            'website_home_intro' => 'nullable|string|max:900',
            'website_home_highlights' => 'nullable|string|max:1200',
            'alts' => 'nullable|array',
            'alts.*' => 'nullable|string|max:255',
        ];
    }

    public static function heroes(): array
    {
        try {
            $decoded = json_decode((string) Setting::get('website_hero_images'), true) ?: [];
        } catch (\Throwable) {
            return []; // settings table not migrated yet (schema-less renders)
        }

        return array_values(array_filter(
            is_array($decoded) ? $decoded : [],
            fn ($hero) => isset($hero['path']) && is_string($hero['path'])
        ));
    }

    public static function stats(): array
    {
        $decoded = json_decode((string) Setting::get('website_stats'), true) ?: [];

        return is_array($decoded) ? array_slice($decoded, 0, 4) : [];
    }

    public static function highlights(): array
    {
        $decoded = json_decode((string) Setting::get('website_home_highlights'), true) ?: [];

        return is_array($decoded) ? array_slice($decoded, 0, 6) : [];
    }

    private function authorizeHomepageManagement(): void
    {
        Gate::authorize('public_homepage.manage');
    }
}
