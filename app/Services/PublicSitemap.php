<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Category;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Page;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\Video;
use Generator;
use Illuminate\Support\Facades\Route;

class PublicSitemap
{
    // Conservative bound: well below both 50,000 URLs and 50 MB per document.
    public function __construct(private int $pageSize = 5000) {}

    private function queries(): array
    {
        $rootRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => ! str_contains($route->uri(), '/') && ! str_contains($route->uri(), '{'));
        $reservedPosts = $rootRoutes->map(fn ($route) => $route->uri())->values()->all();
        $reserved = $rootRoutes->filter(fn ($route) => ! isset($route->defaults['pageSlug']))
            ->map(fn ($route) => $route->uri())->values()->all();
        $pages = Page::where('is_published', true)->whereNotIn('slug', $reserved);
        $hasArticles = fn ($query) => $query->published()->where('noindex', false);

        return [
            'posts' => [Post::published()->where('noindex', false)->whereNotIn('slug', $reservedPosts)
                ->whereNotIn('slug', (clone $pages)->select('slug')), 'post.show'],
            'pages' => [$pages, 'post.show'],
            'categories' => [Category::where('is_active', true)->whereHas('posts', $hasArticles), 'category.show'],
            'tags' => [Tag::whereHas('posts', $hasArticles), 'tag.show'],
            'topics' => [Topic::whereHas('posts', $hasArticles), 'topic.show'],
            'authors' => [Author::whereHas('posts', $hasArticles), 'author.show'],
            'videos' => [Video::published(), 'video.show'],
            'courses' => [Course::where('status', 'published'), 'course.show'],
            'paths' => [LearningPath::where('status', 'published'), 'path.show'],
            'quizzes' => [Quiz::where('status', 'published'), 'quiz.show'],
        ];
    }

    private function directories(array $counts): array
    {
        $urls = [['url' => route('home')]];
        foreach (['categories' => 'categories.index', 'topics' => 'topics.index', 'videos' => 'videos.index', 'courses' => 'courses.index', 'paths' => 'paths.index'] as $kind => $route) {
            if ($counts[$kind] > 0) {
                $urls[] = ['url' => route($route)];
            }
        }

        // No reliable persisted modification clock for these composite pages.
        return $urls;
    }

    public function document(?string $kind = null, int $page = 1): array
    {
        $queries = $this->queries();
        $counts = [];
        foreach ($queries as $key => [$query]) {
            $counts[$key] = (clone $query)->count();
        }
        $directories = $this->directories($counts);
        $counts['directories'] = count($directories);
        if ($kind !== null) {
            abort_unless(isset($counts[$kind]) && $page > 0 && $page <= (int) ceil($counts[$kind] / $this->pageSize), 404);

            return ['index' => false, 'urls' => $kind === 'directories' ? array_slice($directories, ($page - 1) * $this->pageSize, $this->pageSize) : $this->entries($queries[$kind], $kind, $page)];
        }
        if (array_sum($counts) > $this->pageSize) {
            $urls = [];
            foreach ($counts as $key => $count) {
                for ($number = 1; $number <= (int) ceil($count / $this->pageSize); $number++) {
                    $urls[] = ['url' => route('sitemap.part', ['kind' => $key, 'page' => $number])];
                }
            }

            return ['index' => true, 'urls' => $urls];
        }

        return ['index' => false, 'urls' => (function () use ($directories, $queries) {
            yield from $directories;
            foreach ($queries as $key => $query) {
                yield from $this->entries($query, $key);
            }
        })()];
    }

    private function entries(array $definition, string $kind, int $page = 1): Generator
    {
        [$query, $route] = $definition;
        $columns = ['id', 'slug', 'updated_at'];
        if (in_array($kind, ['posts', 'videos'], true)) {
            $columns[] = 'published_at';
        }
        foreach ($query->orderBy('id')->forPage($page, $this->pageSize)->get($columns) as $item) {
            $entry = ['url' => route($route, $item->slug)];
            if (! in_array($kind, ['categories', 'tags', 'topics', 'authors'], true)) {
                $modified = $item->updated_at;
                if ($item->published_at && (! $modified || $item->published_at->gt($modified))) {
                    $modified = $item->published_at;
                }
                if ($modified) {
                    $entry['lastmod'] = $modified->toAtomString();
                }
            }
            yield $entry;
        }
    }
}
