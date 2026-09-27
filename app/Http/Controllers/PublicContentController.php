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

    public function home()
    {
        return view('public.home', [
            'featured' => Post::published()->with(['category', 'author'])->where('is_featured', true)->latest('published_at')->take(3)->get(),
            'posts' => Post::published()->with(['category', 'author'])->latest('published_at')->paginate(12),
            'topics' => Topic::orderBy('name')->take(12)->get(),
            'videos' => Video::published()->latest('published_at')->take(4)->get(),
            'quizzes' => Quiz::where('status', 'published')->latest()->take(4)->get(),
            'paths' => LearningPath::where('status', 'published')->latest()->take(4)->get(),
            'courses' => Course::where('status', 'published')->latest()->take(4)->get(),
        ]);
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->get('q'));
        $type = in_array($request->get('type'), ['all', 'articles', 'videos', 'courses'], true) ? $request->get('type') : 'all';
        $posts = Post::published()->with(['category', 'author'])->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$term.'%')->orWhere('excerpt', 'like', '%'.$term.'%')->orWhere('content_plain', 'like', '%'.$term.'%')->orWhereHas('tags', fn ($tags) => $tags->where('name', 'like', '%'.$term.'%'))->orWhereHas('topics', fn ($topics) => $topics->where('name', 'like', '%'.$term.'%'))->orWhereHas('author', fn ($authors) => $authors->where('name', 'like', '%'.$term.'%'))))->latest('published_at')->paginate(12)->withQueryString();
        $videos = in_array($type, ['all', 'videos']) && $term !== '' ? Video::published()->where('title', 'like', '%'.$term.'%')->take(12)->get() : collect();
        $courses = in_array($type, ['all', 'courses']) && $term !== '' ? Course::where('status', 'published')->where('title', 'like', '%'.$term.'%')->take(12)->get() : collect();

        return view('public.archive', ['title' => $term ? 'Hasil pencarian: '.$term : 'Cari artikel', 'description' => '', 'posts' => $posts, 'search' => $term, 'noindex' => true, 'type' => $type, 'videos' => $videos, 'courses' => $courses]);
    }

    public function category(Category $category)
    {
        abort_unless($category->is_active, 404);

        return $this->archive($category, 'Kategori');
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
