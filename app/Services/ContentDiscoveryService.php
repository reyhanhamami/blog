<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ContentDiscoveryService
{
    public const CONTENT_TYPES = [
        'article' => 'Artikel',
        'tutorial' => 'Tutorial',
        'guide' => 'Panduan',
        'news' => 'Berita',
        'opinion' => 'Opini',
        'case_study' => 'Studi kasus',
        'video_article' => 'Artikel video',
    ];

    public const SORTS = ['latest' => 'Terbaru', 'oldest' => 'Terlama', 'popular' => 'Terpopuler'];

    public const DIFFICULTIES = ['beginner' => 'Pemula', 'intermediate' => 'Menengah', 'advanced' => 'Lanjutan'];

    public function articles(Request $request, ?Category $fixedCategory = null): array
    {
        $query = $this->parameter($request, 'q', 120);
        $categorySlug = $this->parameter($request, 'category');
        $category = $fixedCategory ?: Category::query()->where('is_active', true)->where('slug', $categorySlug)->first();
        $topicSlug = $this->parameter($request, 'topic');
        $topic = Topic::query()->where('slug', $topicSlug)->first();
        $rawType = $this->parameter($request, $fixedCategory ? 'type' : 'content_type');
        if (! $fixedCategory && $rawType === '' && isset(self::CONTENT_TYPES[$this->parameter($request, 'type')])) {
            $rawType = $this->parameter($request, 'type');
        }
        $contentType = isset(self::CONTENT_TYPES[$rawType]) ? $rawType : '';
        $rawDifficulty = $this->parameter($request, 'difficulty');
        $difficulty = isset(self::DIFFICULTIES[$rawDifficulty]) ? $rawDifficulty : '';
        $rawSort = $this->parameter($request, 'sort');
        $sort = isset(self::SORTS[$rawSort]) ? $rawSort : 'latest';

        $posts = $this->publishedArticles()
            ->with(['category', 'author'])
            ->when($category, fn (Builder $builder) => $builder->where('category_id', $category->id))
            ->when($topic, fn (Builder $builder) => $builder->whereHas('topics', fn (Builder $topics) => $topics->whereKey($topic->id)))
            ->when($contentType !== '', fn (Builder $builder) => $builder->where('content_type', $contentType))
            ->when($difficulty !== '', fn (Builder $builder) => $builder->where('difficulty', $difficulty))
            ->when($query !== '', function (Builder $builder) use ($query) {
                $term = '%'.$query.'%';
                $builder->where(function (Builder $search) use ($term) {
                    $search->where('title', 'like', $term)->orWhere('excerpt', 'like', $term)
                        ->orWhere('content_plain', 'like', $term)
                        ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', $term))
                        ->orWhereHas('topics', fn (Builder $topics) => $topics->where('name', 'like', $term))
                        ->orWhereHas('author', fn (Builder $authors) => $authors->where('name', 'like', $term));
                });
            });

        match ($sort) {
            'oldest' => $posts->orderBy('published_at')->orderBy('id'),
            'popular' => $posts->orderByDesc('views')->orderByDesc('published_at')->orderByDesc('id'),
            default => $posts->orderByDesc('published_at')->orderByDesc('id'),
        };

        $categories = Category::query()->where('is_active', true)
            ->whereHas('posts', fn (Builder $builder) => $builder->published()->where('noindex', false))
            ->withCount(['posts as published_posts_count' => fn (Builder $builder) => $builder->published()->where('noindex', false)])
            ->orderBy('name')->get();
        $topics = Topic::query()->whereHas('posts', fn (Builder $builder) => $builder->published()->where('noindex', false)
            ->when($fixedCategory, fn (Builder $posts) => $posts->where('category_id', $fixedCategory->id)))
            ->orderBy('name')->get(['id', 'name', 'slug']);
        $availableTypes = $this->publishedArticles()
            ->when($fixedCategory, fn (Builder $builder) => $builder->where('category_id', $fixedCategory->id))
            ->distinct()->pluck('content_type')->all();
        $availableDifficulties = $this->publishedArticles()
            ->when($fixedCategory, fn (Builder $builder) => $builder->where('category_id', $fixedCategory->id))
            ->whereNotNull('difficulty')->distinct()->pluck('difficulty')->all();

        return [
            'posts' => $posts->paginate(12)->withQueryString(),
            'categories' => $categories,
            'topics' => $topics,
            'contentTypes' => array_intersect_key(self::CONTENT_TYPES, array_flip($availableTypes)),
            'difficulties' => array_intersect_key(self::DIFFICULTIES, array_flip($availableDifficulties)),
            'filters' => compact('query', 'category', 'topic', 'contentType', 'difficulty', 'sort'),
        ];
    }

    private function publishedArticles(): Builder
    {
        return Post::query()->published()->where('noindex', false);
    }

    private function parameter(Request $request, string $key, int $maxLength = 80): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim(mb_substr($value, 0, $maxLength)) : '';
    }
}
