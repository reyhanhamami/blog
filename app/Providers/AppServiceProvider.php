<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::before(fn (User $user) => $user->role === 'superadmin' ? true : null);
        foreach (PermissionCatalog::names() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        // Legacy controller gates remain while route permissions enforce each action.
        Gate::define('manage-content', fn (User $user) => $user->can('cms.access'));
        Gate::define('manage-system', fn (User $user) => $user->can('cms.access'));
    }
}
