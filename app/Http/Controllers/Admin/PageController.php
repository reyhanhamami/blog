<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-content');

        return view('admin.pages.index', ['pages' => Page::latest()->paginate(20)]);
    }

    public function create()
    {
        Gate::authorize('manage-content');

        return view('admin.pages.form', ['page' => new Page]);
    }

    public function edit(Page $page)
    {
        Gate::authorize('manage-content');

        return view('admin.pages.form', compact('page'));
    }

    public function store(Request $request, HtmlSanitizer $sanitizer)
    {
        Gate::authorize('manage-content');
        $page = new Page;
        $this->save($request, $page, $sanitizer);

        return redirect()->route('admin.pages.index')->with('success', 'Halaman dibuat.');
    }

    public function update(Request $request, Page $page, HtmlSanitizer $sanitizer)
    {
        Gate::authorize('manage-content');
        $this->save($request, $page, $sanitizer);

        return redirect()->route('admin.pages.index')->with('success', 'Halaman diperbarui.');
    }

    public function destroy(Page $page)
    {
        Gate::authorize('manage-content');
        $page->delete();

        return back()->with('success', 'Halaman dihapus.');
    }

    private function save(Request $request, Page $page, HtmlSanitizer $sanitizer): void
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191'], 'slug' => ['nullable', 'string', 'max:191', Rule::unique('pages')->ignore($page->id)], 'content' => ['required', 'string'], 'is_published' => ['nullable', 'boolean']]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        if (Post::withTrashed()->where('slug', $data['slug'])->exists() || in_array($data['slug'], ['admin', 'login', 'register', 'forgot-password', 'akun', 'cari'], true)) {
            throw ValidationException::withMessages(['slug' => 'Slug ini sudah digunakan.']);
        }
        $data['content'] = $sanitizer->clean($data['content']);
        $data['is_published'] = $request->boolean('is_published');
        $page->fill($data)->save();
    }
}
