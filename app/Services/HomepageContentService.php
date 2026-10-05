<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Setting;
use App\Support\HomeHeroSettings;

class HomepageContentService
{
    public function content(): array
    {
        $publicPosts = fn () => Post::published()->where('noindex', false)
            ->with('category');

        $featuredPosts = $publicPosts()->where('is_featured', true)
            ->orderByDesc('published_at')->limit(3)->get();

        if ($featuredPosts->isEmpty()) {
            $featuredPosts = $publicPosts()->orderByDesc('published_at')->limit(1)->get();
        }

        $primaryPost = $featuredPosts->first();
        $latestPosts = $publicPosts()
            ->when($featuredPosts->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $featuredPosts->modelKeys()))
            ->orderByDesc('published_at')->limit(4)->get();

        $popularCategories = Category::query()->where('is_active', true)
            ->whereHas('posts', fn ($query) => $query->published()->where('noindex', false))
            ->withCount(['posts as published_posts_count' => fn ($query) => $query->published()->where('noindex', false)])
            ->orderByDesc('published_posts_count')->orderBy('sort_order')->limit(6)->get();

        $heroTagline = Setting::valueFor('tagline', 'Artikel, tutorial, dan strategi digital untuk membantu bisnis Anda tumbuh lebih cepat.');
        $defaultImage = Setting::valueFor('default_og_image');
        $hero = HomeHeroSettings::content();

        return compact('featuredPosts', 'primaryPost', 'latestPosts', 'popularCategories', 'heroTagline', 'defaultImage', 'hero');
    }
}
