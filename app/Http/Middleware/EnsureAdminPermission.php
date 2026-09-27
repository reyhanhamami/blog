<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $required = $this->permissions($request->route()?->getName());
        abort_unless($required && $request->user()?->canAny($required), 403);

        return $next($request);
    }

    private function permissions(?string $name): array
    {
        if (! $name || ! str_starts_with($name, 'admin.')) {
            return [];
        }

        $action = substr($name, 6);
        if ($action === 'dashboard') {
            return ['dashboard.view'];
        }
        if ($action === 'analytics') {
            return ['analytics.view'];
        }
        if ($action === 'editorial-calendar') {
            return ['editorial-calendar.view'];
        }

        if (str_starts_with($action, 'posts.')) {
            $part = substr($action, 6);
            if (in_array($part, ['index', 'preview', 'revisions', 'sources.index', 'bulk'], true)) {
                return ['articles.view'];
            }
            if (in_array($part, ['create', 'store', 'duplicate'], true)) {
                return ['articles.create'];
            }
            if ($part === 'destroy') {
                return ['articles.delete', 'articles.delete-own'];
            }
            if ($part === 'restore') {
                return ['articles.restore'];
            }
            if ($part === 'force') {
                return ['articles.force-delete'];
            }

            return ['articles.update', 'articles.update-own'];
        }

        $simple = [
            'users' => 'users', 'roles' => 'roles', 'settings' => 'settings', 'menus' => 'menus',
            'redirects' => 'redirects', 'pages' => 'pages', 'media' => 'media', 'questions' => 'qa',
        ];
        foreach ($simple as $prefix => $permission) {
            if (! str_starts_with($action, $prefix.'.')) {
                continue;
            }
            $part = substr($action, strlen($prefix) + 1);
            if ($prefix === 'users') {
                return $part === 'index' ? ['users.view', 'roles.view'] : [$permission.'.'.match ($part) {
                    'create', 'store' => 'create', 'destroy' => 'delete', default => 'update',
                }];
            }
            if ($prefix === 'roles') {
                return [$permission.'.'.($part === 'index' || $part === 'show' ? 'view' : 'manage')];
            }
            if ($prefix === 'media') {
                return [$permission.'.'.match ($part) {
                    'index' => 'view', 'destroy' => 'delete', default => 'upload',
                }];
            }
            if ($prefix === 'questions') {
                return [$permission.'.'.(in_array($part, ['index', 'show'], true) ? 'view' : 'moderate')];
            }
            if (in_array($prefix, ['settings', 'redirects', 'pages', 'menus'], true)) {
                return [$permission.'.'.($part === 'index' || ($prefix === 'settings' && $part === 'edit') ? 'view' : 'manage')];
            }
        }

        foreach (['categories', 'tags', 'topics', 'authors', 'videos', 'quizzes', 'learning-paths', 'courses'] as $module) {
            if (! str_starts_with($action, $module.'.')) {
                continue;
            }
            $part = substr($action, strlen($module) + 1);
            if ($module === 'authors') {
                return ['authors.'.($part === 'index' ? 'view' : 'manage')];
            }
            if (in_array($part, ['items.index', 'content.index'], true)) {
                return [$module.'.update'];
            }
            if ($part === 'index' || str_ends_with($part, '.index')) {
                return [$module.'.view'];
            }
            if (in_array($part, ['create', 'store'], true)) {
                return [$module.'.create'];
            }
            if (str_ends_with($part, '.destroy') || $part === 'destroy') {
                return [$module.'.delete'];
            }
            if (str_ends_with($part, '.store')) {
                return [$module.'.update'];
            }
            if ($part === 'restore') {
                return [$module.'.update'];
            }

            return [$module.'.update'];
        }

        return [];
    }
}
