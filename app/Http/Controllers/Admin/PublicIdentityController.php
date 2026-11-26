<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\SiteIdentity;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PublicIdentityController extends Controller
{
    /** @var array<int, string> */
    private const EDITABLE_KEYS = [
        'address',
        'phone',
        'email',
        'public_website_placeholder',
        'public_location',
        'public_office_hours',
        'public_directions_url',
        'public_visit_instructions',
    ];

    public function index(): JsonResponse
    {
        Gate::authorize('public_identity.manage');

        return response()->json([
            'identity' => SiteIdentity::publicProfile(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize('public_identity.manage');
        $validated = $request->validate([
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'public_website_placeholder' => ['nullable', 'string', 'max:255'],
            'public_location' => ['nullable', 'string', 'max:500'],
            'public_office_hours' => ['nullable', 'string', 'max:1000'],
            'public_directions_url' => ['nullable', 'string', 'max:2048', $this->safeHttpUrlRule()],
            'public_visit_instructions' => ['nullable', 'string', 'max:3000'],
        ]);

        foreach (self::EDITABLE_KEYS as $key) {
            Setting::set($key, trim((string) ($validated[$key] ?? '')));
        }

        return response()->json(["message" => 'Verified public contact and visit details updated.']);
    }

    private function safeHttpUrlRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $url = trim((string) $value);
            if ($url === '') {
                return;
            }

            if (! SiteIdentity::isSafeHttpUrl($url)) {
                $fail('The :attribute must be a valid http or https URL.');
            }
        };
    }
}
