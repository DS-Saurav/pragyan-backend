<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddPublicSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request));
    }

    /**
     * Apply the small, compatibility-safe browser security baseline.
     *
     * A Content-Security-Policy is intentionally not set here. The public
     * shell currently relies on external font and icon stylesheets as well as
     * an inline brand-variable style, so a policy needs a separate, tested
     * asset migration or nonce strategy before it can be enforced safely.
     */
    public static function apply(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');

        if ($response->getStatusCode() >= 400
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
