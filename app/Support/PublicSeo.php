<?php

namespace App\Support;

class PublicSeo
{
    public static function canonical(string $url): string
    {
        $url = preg_split('/[?#]/', trim($url))[0];
        $page = filter_var(request()->query('page'), FILTER_VALIDATE_INT);
        $paginated = request()->routeIs('category.show', 'tag.show', 'topic.show', 'author.show', 'categories.index', 'topics.index', 'videos.index', 'courses.index', 'paths.index');

        return $paginated && $page > 1 ? $url.'?page='.$page : $url;
    }

    public static function image(?string $url): string
    {
        $url = trim($url ?? '');
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }
        if (! preg_match('~^https?://~i', $url)) {
            return url('/'.ltrim($url, '/'));
        }

        // Upgrade only our own origin; external image servers may not support TLS.
        return parse_url($url, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST)
            && app()->environment('production') ? preg_replace('~^http://~i', 'https://', $url) : $url;
    }
}
