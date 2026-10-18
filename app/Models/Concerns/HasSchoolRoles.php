<?php

namespace App\Models\Concerns;

use App\Models\UserRole;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasSchoolRoles
{
    private ?array $roleNamesCache = null;

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function roleNames(): array
    {
        return $this->roleNamesCache ??= $this->userRoles()->pluck('role')->all();
    }

    public function hasRole(string ...$roles): bool
    {
        return (bool) array_intersect($roles, $this->roleNames());
    }

    public function hasPermission(string $permission): bool
    {
        foreach ($this->roleNames() as $role) {
            $allowed = config("permissions.roles.{$role}", []);

            if (in_array('*', $allowed, true) || in_array($permission, $allowed, true)) {
                return true;
            }
        }

        return false;
    }
}
