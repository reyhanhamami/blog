<?php

namespace App\Providers;

use App\Models\Post;
use App\Policies\PostPolicy;
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
        Gate::before(fn ($user) => $user->role === 'superadmin' ? true : null);
        Gate::define('manage-content', fn ($user) => in_array($user->role, ['admin', 'editor'], true));
        Gate::define('manage-system', fn ($user) => $user->role === 'admin');
    }
}
