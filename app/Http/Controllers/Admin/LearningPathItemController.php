<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\LearningPathItem;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LearningPathItemController extends Controller
{
    public function index(LearningPath $path)
    {
        Gate::authorize('manage-content');

        return view('admin.learning-items.index', ['path' => $path->load('items.post'), 'posts' => Post::published()->orderBy('title')->get(['id', 'title'])]);
    }

    public function store(Request $request, LearningPath $path)
    {
        Gate::authorize('manage-content');
        $data = $request->validate(['post_id' => ['required', 'exists:posts,id'], 'sort_order' => ['required', 'integer', 'min:0']]);
        $path->items()->updateOrCreate(['post_id' => $data['post_id']], ['sort_order' => $data['sort_order']]);

        return back()->with('success', 'Materi ditambahkan.');
    }

    public function update(Request $request, LearningPath $path, LearningPathItem $item)
    {
        Gate::authorize('manage-content');
        abort_unless($item->learning_path_id === $path->id, 404);
        $item->update($request->validate(['sort_order' => ['required', 'integer', 'min:0']]));

        return back()->with('success', 'Urutan materi diperbarui.');
    }

    public function destroy(LearningPath $path, LearningPathItem $item)
    {
        Gate::authorize('manage-content');
        abort_unless($item->learning_path_id === $path->id, 404);
        $item->delete();

        return back()->with('success', 'Materi dihapus dari jalur belajar.');
    }
}
