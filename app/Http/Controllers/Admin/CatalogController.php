<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Category;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Quiz;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public const MODULES = [
        'categories' => ['model' => Category::class, 'label' => 'Kategori', 'fields' => ['name' => 'text', 'description' => 'textarea', 'parent_id' => 'category', 'sort_order' => 'number', 'is_active' => 'checkbox']],
        'tags' => ['model' => Tag::class, 'label' => 'Tag', 'fields' => ['name' => 'text', 'description' => 'textarea']],
        'topics' => ['model' => Topic::class, 'label' => 'Topik', 'fields' => ['name' => 'text', 'description' => 'textarea']],
        'authors' => ['model' => Author::class, 'label' => 'Penulis', 'fields' => ['name' => 'text', 'job_title' => 'text', 'short_bio' => 'textarea', 'full_bio' => 'textarea', 'expertise' => 'text', 'website' => 'url', 'github_url' => 'url', 'linkedin_url' => 'url', 'youtube_url' => 'url']],
        'videos' => ['model' => Video::class, 'label' => 'Video', 'fields' => ['title' => 'text', 'description' => 'textarea', 'youtube_id' => 'youtube', 'status' => 'status']],
        'quizzes' => ['model' => Quiz::class, 'label' => 'Kuis', 'fields' => ['title' => 'text', 'description' => 'textarea', 'passing_score' => 'number', 'status' => 'status']],
        'learning-paths' => ['model' => LearningPath::class, 'label' => 'Jalur Belajar', 'fields' => ['title' => 'text', 'description' => 'textarea', 'status' => 'status']],
        'courses' => ['model' => Course::class, 'label' => 'Kelas', 'fields' => ['title' => 'text', 'description' => 'textarea', 'status' => 'status']],
    ];

    private function config(Request $request): array
    {
        return self::MODULES[$request->route('module')];
    }

    private function authorize(Request $request): void
    {
        Gate::authorize('manage-content');
    }

    public function index(Request $request)
    {
        $this->authorize($request);
        $config = $this->config($request);
        $module = $request->route('module');
        $query = $config['model']::query();
        if ($request->boolean('trash') && in_array($module, ['categories', 'videos', 'quizzes', 'learning-paths', 'courses'])) {
            $query->onlyTrashed();
        }
        if ($request->filled('q')) {
            $query->where($module === 'videos' || in_array($module, ['quizzes', 'learning-paths', 'courses']) ? 'title' : 'name', 'like', '%'.$request->q.'%');
        }
        $items = $query->latest()->paginate(15)->withQueryString();

        return view('admin.catalog.index', compact('items', 'config', 'module'));
    }

    public function create(Request $request)
    {
        $this->authorize($request);
        $config = $this->config($request);
        $module = $request->route('module');
        $item = new $config['model'];

        return view('admin.catalog.form', compact('item', 'config', 'module') + ['parents' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorize($request);
        $config = $this->config($request);
        $module = $request->route('module');
        $item = new $config['model'];
        $this->save($request, $item, $config, $module);

        return redirect()->route('admin.'.$module.'.index')->with('success', $config['label'].' berhasil dibuat.');
    }

    public function edit(Request $request)
    {
        $this->authorize($request);
        $config = $this->config($request);
        $module = $request->route('module');
        $item = $config['model']::findOrFail($request->route('item'));

        return view('admin.catalog.form', compact('item', 'config', 'module') + ['parents' => Category::orderBy('name')->get()]);
    }

    public function update(Request $request)
    {
        $this->authorize($request);
        $config = $this->config($request);
        $module = $request->route('module');
        $item = $config['model']::findOrFail($request->route('item'));
        $this->save($request, $item, $config, $module);

        return redirect()->route('admin.'.$module.'.index')->with('success', $config['label'].' berhasil diperbarui.');
    }

    public function destroy(Request $request)
    {
        $this->authorize($request);
        $config = $this->config($request);
        $item = $config['model']::findOrFail($request->route('item'));
        $item->delete();

        return back()->with('success', $config['label'].' berhasil dihapus.');
    }

    public function restore(Request $request)
    {
        Gate::authorize('manage-system');
        $config = $this->config($request);
        $item = $config['model']::onlyTrashed()->findOrFail($request->route('item'));
        $item->restore();

        return back()->with('success', $config['label'].' dipulihkan.');
    }

    private function save(Request $request, $item, array $config, string $module): void
    {
        $rules = [];
        foreach ($config['fields'] as $field => $type) {
            $rules[$field] = match ($type) {
                'text' => [in_array($field, ['name', 'title']) ? 'required' : 'nullable', 'string', 'max:191'],
                'textarea' => ['nullable', 'string', 'max:10000'],
                'url' => ['nullable', 'url', 'max:2048'],
                'number' => ['nullable', 'integer', 'min:0', 'max:'.($field === 'passing_score' ? 100 : 100000)],
                'category' => ['nullable', 'exists:categories,id'],
                'status' => ['required', Rule::in(['draft', 'published'])],
                'youtube' => ['required', 'string', 'max:2048'],
                default => ['nullable'],
            };
        }
        $rules['slug'] = ['nullable', 'string', 'max:191'];
        $data = $request->validate($rules);
        $name = $data['title'] ?? $data['name'];
        $data['slug'] = Str::slug(($data['slug'] ?? '') ?: $name);
        $slugQuery = in_array($module, ['categories', 'videos', 'quizzes', 'learning-paths', 'courses'], true) ? $config['model']::withTrashed() : $config['model']::query();
        if ($slugQuery->where('slug', $data['slug'])->when($item->exists, fn ($q) => $q->where('id', '!=', $item->id))->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug sudah digunakan.']);
        }
        if ($module === 'videos') {
            preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([a-zA-Z0-9_-]{11})~', $data['youtube_id'], $match);
            $data['youtube_id'] = $match[1] ?? $data['youtube_id'];
            if (! preg_match('/^[a-zA-Z0-9_-]{11}$/', $data['youtube_id'])) {
                throw ValidationException::withMessages(['youtube_id' => 'Masukkan URL atau ID YouTube yang valid.']);
            }
            $data['published_at'] = $data['status'] === 'published' ? ($item->published_at ?: now()) : null;
        }
        if ($module === 'categories') {
            $data['is_active'] = $request->boolean('is_active');
            if ($item->exists && ($data['parent_id'] ?? null) == $item->id) {
                throw ValidationException::withMessages(['parent_id' => 'Kategori tidak dapat menjadi induknya sendiri.']);
            }
        }
        $item->fill($data)->save();
    }
}
