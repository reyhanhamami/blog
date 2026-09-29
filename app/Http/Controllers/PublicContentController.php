<?php

namespace App\Http\Controllers;

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
use App\Services\ArticleNavigation;
use App\Services\ContentDiscoveryService;
use App\Services\HomepageContentService;
use App\Services\QuizGrader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicContentController extends Controller
{
    public function page(Request $request)
    {
        $page = Page::where('slug', $request->route('pageSlug'))->where('is_published', true)->firstOrFail();

        return view('public.page', compact('page'));
    }

    public function home(HomepageContentService $homepage)
    {
        return view('public.home', $homepage->content());
    }

    public function categories()
    {
        $categories = Category::query()->where('is_active', true)
            ->whereHas('posts', fn ($query) => $query->published()->where('noindex', false))
            ->withCount(['posts as published_posts_count' => fn ($query) => $query->published()->where('noindex', false)])
            ->orderByDesc('published_posts_count')->paginate(24);

        return view('public.categories', compact('categories'));
    }

    public function topics()
    {
        $topics = Topic::query()
            ->whereHas('posts', fn ($query) => $query->published()->where('noindex', false))
            ->withCount(['posts as published_posts_count' => fn ($query) => $query->published()->where('noindex', false)])
            ->orderBy('name')->paginate(18);

        return view('public.discovery', ['kind' => 'topics', 'title' => 'Jelajahi Topik', 'description' => 'Temukan artikel, tutorial, dan materi berdasarkan bidang yang ingin Anda pelajari.', 'items' => $topics]);
    }

    public function paths()
    {
        $paths = LearningPath::query()->where('status', 'published')
            ->withCount('items')->latest()->paginate(12);

        return view('public.discovery', ['kind' => 'paths', 'title' => 'Jalur Belajar', 'description' => 'Ikuti rangkaian materi yang disusun untuk membantu Anda belajar langkah demi langkah.', 'items' => $paths]);
    }

    public function courses()
    {
        $courses = Course::query()->where('status', 'published')
            ->withCount('modules')->latest()->paginate(12);
        $courseIds = $courses->getCollection()->modelKeys();
        $lessonCounts = DB::table('course_lessons')->join('course_modules', 'course_modules.id', '=', 'course_lessons.course_module_id')
            ->whereIn('course_modules.course_id', $courseIds)
            ->selectRaw('course_modules.course_id, COUNT(*) as total')
            ->groupBy('course_modules.course_id')->pluck('total', 'course_id');
        $completedCounts = collect();
        $lastLessons = collect();
        $nextLessons = collect();
        if (auth()->check()) {
            $completedCounts = DB::table('course_progress')->join('course_lessons', 'course_lessons.id', '=', 'course_progress.course_lesson_id')
                ->join('course_modules', 'course_modules.id', '=', 'course_lessons.course_module_id')
                ->where('course_progress.user_id', auth()->id())->whereIn('course_modules.course_id', $courseIds)
                ->selectRaw('course_modules.course_id, COUNT(*) as total')
                ->groupBy('course_modules.course_id')->pluck('total', 'course_id');
            $lastLessons = DB::table('course_activity')->where('user_id', auth()->id())
                ->whereIn('course_id', $courseIds)->pluck('last_lesson_id', 'course_id');
            $nextLessons = DB::table('course_lessons')->join('course_modules', 'course_modules.id', '=', 'course_lessons.course_module_id')
                ->leftJoin('course_progress', function ($join) {
                    $join->on('course_progress.course_lesson_id', '=', 'course_lessons.id')
                        ->where('course_progress.user_id', auth()->id());
                })
                ->whereIn('course_modules.course_id', $courseIds)->whereNull('course_progress.id')
                ->orderBy('course_modules.sort_order')->orderBy('course_modules.id')
                ->orderBy('course_lessons.sort_order')->orderBy('course_lessons.id')
                ->get(['course_modules.course_id', 'course_lessons.id'])
                ->unique('course_id')->pluck('id', 'course_id');
        }

        return view('public.discovery', ['kind' => 'courses', 'title' => 'Kelas', 'description' => 'Pelajari topik secara lebih mendalam melalui materi yang tersusun dalam modul dan pelajaran.', 'items' => $courses, 'lessonCounts' => $lessonCounts, 'completedCounts' => $completedCounts, 'lastLessons' => $lastLessons, 'nextLessons' => $nextLessons]);
    }

    public function videos()
    {
        $videos = Video::published()->latest('published_at')->paginate(12);

        return view('public.discovery', ['kind' => 'videos', 'title' => 'Video', 'description' => 'Tonton penjelasan dan tutorial dari koleksi Besofton Insights.', 'items' => $videos]);
    }

    public function search(Request $request, ContentDiscoveryService $discovery)
    {
        $data = $discovery->articles($request);
        $term = $data['filters']['query'];
        $requestedType = is_string($request->query('type')) ? $request->query('type') : 'all';
        $resultType = match ($requestedType) {
            'article', 'articles' => 'article',
            'video', 'videos' => 'video',
            'course', 'courses' => 'course',
            'learning', 'paths' => 'learning',
            default => 'all',
        };
        $otherContentAllowed = ! $data['filters']['category'] && ! $data['filters']['topic']
            && $data['filters']['contentType'] === '' && $data['filters']['difficulty'] === '';
        $searchTerm = '%'.$term.'%';
        $videos = $otherContentAllowed && ($resultType === 'video' || ($resultType === 'all' && $term !== ''))
            ? ($resultType === 'video'
                ? Video::published()->when($term !== '', fn ($query) => $query->where('title', 'like', $searchTerm))->latest('published_at')->paginate(12)->withQueryString()
                : Video::published()->where('title', 'like', $searchTerm)->latest('published_at')->take(6)->get()) : collect();
        $courses = $otherContentAllowed && ($resultType === 'course' || ($resultType === 'all' && $term !== ''))
            ? ($resultType === 'course'
                ? Course::where('status', 'published')->when($term !== '', fn ($query) => $query->where('title', 'like', $searchTerm))->latest()->paginate(12)->withQueryString()
                : Course::where('status', 'published')->where('title', 'like', $searchTerm)->latest()->take(6)->get()) : collect();
        $paths = $otherContentAllowed && ($resultType === 'learning' || ($resultType === 'all' && $term !== ''))
            ? ($resultType === 'learning'
                ? LearningPath::where('status', 'published')->when($term !== '', fn ($query) => $query->where('title', 'like', $searchTerm))->latest()->paginate(12)->withQueryString()
                : LearningPath::where('status', 'published')->where('title', 'like', $searchTerm)->latest()->take(6)->get()) : collect();

        return view('public.explore', $data + compact('videos', 'courses', 'paths', 'resultType') + ['context' => 'search', 'currentCategory' => null]);
    }

    public function category(Request $request, Category $category, ContentDiscoveryService $discovery)
    {
        abort_unless($category->is_active, 404);

        return view('public.explore', $discovery->articles($request, $category) + ['context' => 'category', 'currentCategory' => $category]);
    }

    public function tag(Tag $tag)
    {
        return $this->archive($tag, 'Tag');
    }

    public function topic(Topic $topic)
    {
        return $this->archive($topic, 'Topik');
    }

    public function author(Author $author)
    {
        return $this->archive($author, 'Penulis');
    }

    private function archive($entity, string $prefix)
    {
        return view('public.archive', ['title' => $prefix.': '.$entity->name, 'description' => $entity->description ?? $entity->short_bio ?? '', 'posts' => $entity->posts()->published()->with(['category', 'author'])->latest('published_at')->paginate(12), 'search' => null, 'noindex' => false, 'entity' => $entity]);
    }

    public function post(string $slug, ArticleNavigation $navigation)
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->first();
        if ($page) {
            return view('public.page', compact('page'));
        }
        $post = Post::published()->with(['category', 'author', 'tags', 'topics', 'sources', 'quiz'])->where('slug', $slug)->first();
        if (! $post) {
            $redirect = DB::table('redirects')->where('from_path', '/'.$slug)->first();
            if ($redirect) {
                return redirect($redirect->to_path, $redirect->status_code);
            }
            abort(404);
        }
        if (! request()->session()->has('viewed_post_'.$post->id)) {
            $post->increment('views');
            request()->session()->put('viewed_post_'.$post->id, true);
        }
        if (auth()->check()) {
            DB::table('reading_history')->updateOrInsert(['user_id' => auth()->id(), 'post_id' => $post->id], ['last_read_at' => now()]);
        }
        $related = $post->relatedPosts()->published()->with(['category', 'author'])->latest('published_at')->take(3)->get();
        if ($related->isEmpty()) {
            $related = Post::published()->with(['category', 'author'])->whereKeyNot($post->id)->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))->latest('published_at')->take(3)->get();
        }
        $questions = $post->questions()->where('status', 'approved')->with('answers.user')->latest()->take(30)->get();

        $navigationLinks = $navigation->for($post);

        return view('public.post', compact('post', 'related', 'questions') + $navigationLinks + ['preview' => false]);
    }

    public function video(Video $video)
    {
        abort_unless($video->status === 'published' && $video->published_at?->isPast(), 404);

        return view('public.video', compact('video'));
    }

    public function quiz(Quiz $quiz)
    {
        abort_unless($quiz->status === 'published', 404);

        return view('public.quiz', compact('quiz'));
    }

    public function submitQuiz(Request $request, Quiz $quiz, QuizGrader $grader)
    {
        abort_unless($quiz->status === 'published', 404);
        $answers = $request->validate(['answers' => ['required', 'array']])['answers'];
        $result = $grader->grade($quiz, $answers, $request->user()?->id);

        return back()->with('quiz_result', $result);
    }

    public function path(LearningPath $path)
    {
        abort_unless($path->status === 'published', 404);
        $path->load(['items.post', 'items.quiz', 'items.video']);

        return view('public.path', compact('path'));
    }

    public function course(Course $course)
    {
        abort_unless($course->status === 'published', 404);
        $course->load('modules.lessons');

        return view('public.course', compact('course'));
    }

    public function sitemap()
    {
        $urls = collect([['url' => route('home'), 'lastmod' => now()->toAtomString()]]);
        Post::published()->where('noindex', false)->select(['slug', 'updated_at'])->orderBy('id')->chunk(200, function ($posts) use ($urls) {
            foreach ($posts as $post) {
                $urls->push(['url' => url('/'.$post->slug), 'lastmod' => $post->updated_at->toAtomString()]);
            }
        });
        foreach (['categories' => Category::class, 'tags' => Tag::class, 'topics' => Topic::class, 'authors' => Author::class] as $name => $class) {
            foreach ($class::all(['slug', 'updated_at']) as $item) {
                $urls->push(['url' => url('/'.['categories' => 'kategori', 'tags' => 'tag', 'topics' => 'topik', 'authors' => 'author'][$name].'/'.$item->slug), 'lastmod' => $item->updated_at->toAtomString()]);
            }
        }
        foreach (Page::where('is_published', true)->get(['slug', 'updated_at']) as $page) {
            $urls->push(['url' => url('/'.$page->slug), 'lastmod' => $page->updated_at->toAtomString()]);
        }

        return response()->view('public.sitemap', compact('urls'))->header('Content-Type', 'application/xml');
    }

    public function feed()
    {
        $posts = Post::published()->latest('published_at')->take(20)->get();

        return response()->view('public.feed', compact('posts'))->header('Content-Type', 'application/rss+xml');
    }

    public function robots()
    {
        $body = config('app.env') === 'production'
            ? "User-agent: *\nDisallow: /admin\nDisallow: /cari\nSitemap: ".url('/sitemap.xml')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    public function feedback(Request $request, Post $post)
    {
        abort_unless($post->status === 'published' && $post->published_at?->isPast(), 404);
        $data = $request->validate(['helpful' => ['required', 'boolean']]);
        $hash = hash('sha256', ($request->ip() ?: 'unknown').'|'.($request->userAgent() ?: '').'|'.config('app.key'));
        DB::table('article_feedback')->updateOrInsert(['post_id' => $post->id, 'visitor_hash' => $hash], ['helpful' => (bool) $data['helpful'], 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Terima kasih atas masukan Anda.');
    }
}
