<?php

namespace App\Providers;

use App\Services\Media\MediaUsage;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Scoped to media owner aliases; do not globally require aliases for unrelated models.
        Relation::morphMap(MediaUsage::MORPH_MAP);

        // Admin can do everything
        Gate::before(fn ($user) => $user->hasRole('admin') ? true : null);

        Event::listen(Login::class, function (Login $event) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });

        // One gate per permission in config/permissions.php
        foreach (array_keys(config('permissions.permissions', [])) as $permission) {
            Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        }
    }
}
