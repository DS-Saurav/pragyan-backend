<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetPublicLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $locale = 'en'): Response
    {
        abort_unless(in_array($locale, ['en', 'ne'], true), 404);

        app()->setLocale($locale);
        $request->attributes->set('public_locale', $locale);

        return $next($request);
    }
}
