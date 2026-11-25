<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    /**
     * Roles that are explicitly not staff. Every other configured role is staff.
     */
    private const NON_STAFF_ROLES = ['student', 'parent'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasAnyRole($this->staffRoles())) {
            abort(403, 'This area is only available to staff accounts.');
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    private function staffRoles(): array
    {
        $roles = array_keys((array) config('permissions.role_labels', []));

        return array_values(array_diff($roles, self::NON_STAFF_ROLES));
    }
}
