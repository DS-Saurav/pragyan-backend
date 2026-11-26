<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PublicLocalePreferenceController extends Controller
{
    /**
     * Persist only an affirmative language choice. Public URLs always remain
     * authoritative: the cookie is a convenience for a later visit, never a
     * reason to redirect a visitor based on browser or request heuristics.
     */
    public function __invoke(Request $request, string $locale): JsonResponse
    {
        abort_unless(in_array($locale, ['en', 'ne'], true), 404);

        $target = $this->safeTarget($request->query('to'));
        $minutes = 60 * 24 * 365;

        return response()->json(["ok" => true]);
    }

    private function safeTarget(mixed $value): string
    {
        if (! is_string($value)) {
            return '/';
        }

        $target = trim($value);
        if ($target === ''
            || ! str_starts_with($target, '/')
            || str_starts_with($target, '//')
            || str_contains($target, '\\')
            || str_contains($target, "\r")
            || str_contains($target, "\n")) {
            return '/';
        }

        $parts = parse_url($target);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user'])) {
            return '/';
        }

        return $target;
    }
}
