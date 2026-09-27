<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LearningPathItemType;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\LearningPathItem;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LearningPathItemController extends Controller
{
    public function index(LearningPath $path)
    {
        Gate::authorize('manage-content');

        return view('admin.learning-items.index', [
            'path' => $path->load(['items.post', 'items.quiz', 'items.video']),
            'posts' => Post::published()->orderBy('title')->get(['id', 'title']),
            'quizzes' => Quiz::where('status', 'published')->orderBy('title')->get(['id', 'title']),
            'videos' => Video::published()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request, LearningPath $path)
    {
        Gate::authorize('manage-content');
        $data = $request->validate([
            'type' => ['nullable', Rule::enum(LearningPathItemType::class)],
            'post_id' => ['nullable', 'exists:posts,id'],
            'quiz_id' => ['nullable', 'exists:quizzes,id'],
            'video_id' => ['nullable', 'exists:videos,id'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
        $type = LearningPathItemType::from($data['type'] ?? LearningPathItemType::Article->value);
        $key = match ($type) {
            LearningPathItemType::Article => 'post_id',
            LearningPathItemType::Video => 'video_id',
            LearningPathItemType::Quiz => 'quiz_id',
        };
        if (empty($data[$key])) {
            throw ValidationException::withMessages([$key => 'Pilih materi yang sesuai.']);
        }
        $path->items()->updateOrCreate([$key => $data[$key]], [
            'type' => $type,
            'post_id' => $key === 'post_id' ? $data[$key] : null,
            'video_id' => $key === 'video_id' ? $data[$key] : null,
            'quiz_id' => $key === 'quiz_id' ? $data[$key] : null,
            'sort_order' => $data['sort_order'],
        ]);

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
