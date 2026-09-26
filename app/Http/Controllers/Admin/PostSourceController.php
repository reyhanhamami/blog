<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostSourceController extends Controller
{
    public function index(Post $post)
    {
        Gate::authorize('update', $post);

        return view('admin.sources.index', ['post' => $post->load('sources')]);
    }

    public function create(Post $post)
    {
        Gate::authorize('update', $post);

        return view('admin.sources.form', ['post' => $post, 'source' => new PostSource]);
    }

    public function edit(Post $post, PostSource $source)
    {
        Gate::authorize('update', $post);
        abort_unless($source->post_id === $post->id, 404);

        return view('admin.sources.form', compact('post', 'source'));
    }

    public function store(Request $request, Post $post)
    {
        Gate::authorize('update', $post);
        $post->sources()->create($this->validated($request));

        return redirect()->route('admin.posts.sources.index', $post)->with('success', 'Referensi dibuat.');
    }

    public function update(Request $request, Post $post, PostSource $source)
    {
        Gate::authorize('update', $post);
        abort_unless($source->post_id === $post->id, 404);
        $source->update($this->validated($request));

        return redirect()->route('admin.posts.sources.index', $post)->with('success', 'Referensi diperbarui.');
    }

    public function destroy(Post $post, PostSource $source)
    {
        Gate::authorize('update', $post);
        abort_unless($source->post_id === $post->id, 404);
        $source->delete();

        return back()->with('success', 'Referensi dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:191'], 'url' => ['required', 'url', 'max:2048'], 'publisher' => ['nullable', 'string', 'max:191'], 'published_at' => ['nullable', 'date'], 'accessed_at' => ['nullable', 'date'], 'sort_order' => ['required', 'integer', 'min:0']]);
    }
}
