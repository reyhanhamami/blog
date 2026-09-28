<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PublicNavigation
{
    public const CORE = [
        '/' => 'Insights',
        '/topik' => 'Topik',
        '/belajar' => 'Belajar',
        '/kelas' => 'Kelas',
        '/video' => 'Video',
    ];

    public static function fallback(): Collection
    {
        return collect(self::CORE)->map(fn (string $label, string $url) => (object) compact('label', 'url'))->values();
    }

    public static function isActive(string $url): bool
    {
        $routes = match ($url) {
            '/' => ['home', 'post.show', 'categories.index', 'category.show', 'tag.show'],
            '/topik' => ['topics.index', 'topic.show'],
            '/belajar' => ['paths.index', 'path.show'],
            '/kelas' => ['courses.index', 'course.show', 'course.lesson'],
            '/video' => ['videos.index', 'video.show'],
            default => [],
        };

        if ($routes !== []) {
            return request()->routeIs(...$routes);
        }

        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return false;
        }

        return request()->path() === ltrim($path, '/');
    }
}
