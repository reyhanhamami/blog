<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Topic;
use App\Services\Content\PostWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Post::class);
        $query = Post::query()->with(['category', 'author']);
        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$request->q.'%')->orWhere('content_plain', 'like', '%'.$request->q.'%'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->user()->role === 'author') {
            $query->where('created_by', $request->user()->id);
        }
        $sort = in_array($request->get('sort'), ['title', 'created_at', 'published_at'], true) ? $request->sort : 'created_at';
        $posts = $query->orderBy($sort, $request->get('dir') === 'asc' ? 'asc' : 'desc')->paginate(in_array((int) $request->get('per_page'), [15, 25, 50, 100], true) ? (int) $request->per_page : 15)->withQueryString();

        return view('admin.posts.index', compact('posts') + ['categories' => Category::orderBy('name')->get()]);
    }

    public function bulk(Request $request)
    {
        Gate::authorize('viewAny', Post::class);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:posts,id'],
            'action' => ['required', Rule::in(['publish', 'draft', 'archive', 'trash', 'category', 'author'])],
            'category_id' => ['required_if:action,category', 'nullable', 'exists:categories,id'],
            'author_id' => ['required_if:action,author', 'nullable', 'exists:authors,id'],
        ]);
        $posts = Post::whereIn('id', $data['ids'])->get();
        abort_unless($posts->count() === count($data['ids']), 422);
        foreach ($posts as $post) {
            Gate::authorize($data['action'] === 'trash' ? 'delete' : ($data['action'] === 'publish' ? 'publish' : 'update'), $post);
            if ($request->user()->role === 'author' && in_array($data['action'], ['archive', 'category', 'author'], true)) {
                abort(403);
            }
        }
        DB::transaction(function () use ($posts, $data, $request) {
            foreach ($posts as $post) {
                if ($data['action'] === 'trash') {
                    $post->delete();
                } else {
                    if (in_array($data['action'], ['publish', 'draft', 'archive'], true)) {
                        $post->status = ['publish' => 'published', 'draft' => 'draft', 'archive' => 'archived'][$data['action']];
                        $post->published_at = $data['action'] === 'publish' ? ($post->published_at ?? now()) : null;
                        $post->scheduled_at = null;
                    } elseif ($data['action'] === 'category') {
                        $post->category_id = $data['category_id'];
                    } else {
                        $post->author_id = $data['author_id'];
                    }
                    $post->updated_by = $request->user()->id;
                    $post->save();
                }
                DB::table('activities')->insert(['user_id' => $request->user()->id, 'action' => 'bulk_'.$data['action'], 'subject_type' => Post::class, 'subject_id' => $post->id, 'created_at' => now()]);
            }
        });

        return back()->with('success', $posts->count().' artikel berhasil diproses.');
    }
    public function create()
    {
        Gate::authorize('create', Post::class);

        return view('admin.posts.form', $this->formData(new Post));
    }

    public function store(Request $request, PostWriter $writer)
    {
        Gate::authorize('create', Post::class);
        $post = $writer->save(null, $this->validated($request), $request->user());

        return redirect()->route('admin.posts.edit', $post)->with('success', 'Artikel berhasil dibuat.');
    }

    public function edit(Post $post)
    {
        Gate::authorize('update', $post);

        return view('admin.posts.form', $this->formData($post));
    }

    public function update(Request $request, Post $post, PostWriter $writer)
    {
        Gate::authorize('update', $post);
        $writer->save($post, $this->validated($request, $post), $request->user());

        return redirect()->route('admin.posts.edit', $post)->with('success', 'Artikel berhasil diperbarui.');
    }

    public function destroy(Post $post)
    {
        Gate::authorize('delete', $post);
        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', 'Artikel dipindahkan ke Trash.');
    }

    public function restore(int $post)
    {
        $post = Post::onlyTrashed()->findOrFail($post);
        Gate::authorize('restore', $post);
        $post->restore();

        return back()->with('success', 'Artikel dipulihkan.');
    }

    public function forceDelete(int $post)
    {
        $post = Post::onlyTrashed()->findOrFail($post);
        Gate::authorize('forceDelete', $post);
        $post->forceDelete();

        return back()->with('success', 'Artikel dihapus permanen.');
    }

    public function preview(Post $post)
    {
        Gate::authorize('view', $post);

        return view('public.post', ['post' => $post->load(['author', 'category', 'tags', 'topics', 'sources']), 'preview' => true, 'related' => collect(), 'questions' => collect()]);
    }

    public function duplicate(Post $post)
    {
        Gate::authorize('view', $post);
        Gate::authorize('create', Post::class);
        $copy = DB::transaction(function () use ($post) {
            $copy = $post->replicate(['views', 'published_at', 'scheduled_at']);
            $copy->title = $post->title.' (Salinan)';
            $copy->slug = Str::slug($post->slug.'-salinan-'.Str::lower(Str::random(5)));
            $copy->status = 'draft';
            $copy->created_by = auth()->id();
            $copy->updated_by = auth()->id();
            $copy->save();
            $copy->tags()->sync($post->tags->modelKeys());
            $copy->topics()->sync($post->topics->modelKeys());
            $copy->relatedPosts()->sync($post->relatedPosts()->pluck('posts.id')->all());

            return $copy;
        });

        return redirect()->route('admin.posts.edit', $copy)->with('success', 'Artikel berhasil diduplikasi.');
    }

    public function revisions(Post $post)
    {
        Gate::authorize('view', $post);
        $revisions = DB::table('post_revisions')->where('post_id', $post->id)->orderByDesc('version')->paginate(20);

        return view('admin.posts.revisions', compact('post', 'revisions'));
    }

    public function restoreRevision(Post $post, int $version, PostWriter $writer)
    {
        Gate::authorize('update', $post);
        $revision = DB::table('post_revisions')->where('post_id', $post->id)->where('version', $version)->first();
        abort_unless($revision, 404);
        $snapshot = json_decode($revision->snapshot, true);
        $data = array_merge($post->toArray(), $snapshot);
        $data['tags'] = $post->tags()->pluck('tags.id')->all();
        $data['topics'] = $post->topics()->pluck('topics.id')->all();
        $writer->save($post, $data, request()->user());

        return redirect()->route('admin.posts.edit', $post)->with('success', 'Revisi dipulihkan sebagai versi baru.');
    }

    private function formData(Post $post): array
    {
        $post->loadMissing(['tags', 'topics', 'relatedPosts']);

        return ['post' => $post, 'authors' => Author::orderBy('name')->get(), 'categories' => Category::orderBy('name')->get(), 'tags' => Tag::orderBy('name')->get(), 'topics' => Topic::orderBy('name')->get(), 'relatedOptions' => Post::published()->when($post->exists, fn ($q) => $q->where('id', '!=', $post->id))->orderBy('title')->get(['id', 'title'])];
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        $statuses = in_array($request->user()->role, ['author'], true) ? ['draft', 'pending_review'] : ['draft', 'pending_review', 'scheduled', 'published', 'archived'];
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'author_id' => ['nullable', 'exists:authors,id'],
            'content_type' => ['required', Rule::in(['article', 'tutorial', 'guide', 'news', 'opinion', 'case_study', 'video_article'])],
            'difficulty' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'status' => ['required', Rule::in($statuses)],
            'scheduled_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'featured_image' => ['nullable', 'url', 'max:2048'],
            'featured_image_alt' => ['nullable', 'string', 'max:191'],
            'seo_title' => ['nullable', 'string', 'max:191'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'og_title' => ['nullable', 'string', 'max:191'],
            'og_description' => ['nullable', 'string', 'max:300'],
            'og_image' => ['nullable', 'url', 'max:2048'],
            'direct_answer' => ['nullable', 'string', 'max:2000'],
            'key_takeaways' => ['nullable', 'string', 'max:5000'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array'], 'tags.*' => ['integer', 'exists:tags,id'],
            'topics' => ['nullable', 'array'], 'topics.*' => ['integer', 'exists:topics,id'],
            'related_posts' => ['nullable', 'array'], 'related_posts.*' => ['integer', 'exists:posts,id'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        if (in_array($data['slug'], ['admin', 'login', 'register', 'forgot-password', 'akun', 'cari', 'sitemap.xml', 'feed.xml', 'robots.txt'], true) || Page::where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug ini digunakan halaman sistem.']);
        }
        if (Post::withTrashed()->where('slug', $data['slug'])->when($post, fn ($q) => $q->whereKeyNot($post->id))->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug sudah digunakan.']);
        }
        $data['is_featured'] = $request->boolean('is_featured');
        $data['noindex'] = $request->boolean('noindex');

        return $data;
    }
}
