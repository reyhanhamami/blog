<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage-content');
        $media = Media::query()->when($request->filled('q'), fn ($q) => $q->where('alt_text', 'like', '%'.$request->q.'%')->orWhere('caption', 'like', '%'.$request->q.'%'))->latest()->paginate(24)->withQueryString();

        return view('admin.media.index', compact('media'));
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-content');
        $data = $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'], 'alt_text' => ['required', 'string', 'max:191'], 'caption' => ['nullable', 'string', 'max:1000']]);
        $file = $data['file'];
        $folder = 'uploads/'.now()->format('Y/m');
        File::ensureDirectoryExists(public_path($folder));
        $filename = Str::random(40).'.'.$file->guessExtension();
        $file->move(public_path($folder), $filename);
        $path = $folder.'/'.$filename;
        $dimensions = @getimagesize(public_path($path));
        Media::create(['path' => $path, 'mime_type' => mime_content_type(public_path($path)), 'size' => filesize(public_path($path)), 'width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null, 'alt_text' => $data['alt_text'], 'caption' => $data['caption'] ?? null, 'uploaded_by' => $request->user()->id]);

        return back()->with('success', 'Gambar berhasil diunggah.');
    }

    public function update(Request $request, Media $media)
    {
        Gate::authorize('manage-content');
        $media->update($request->validate(['alt_text' => ['required', 'string', 'max:191'], 'caption' => ['nullable', 'string', 'max:1000']]));

        return back()->with('success', 'Metadata gambar diperbarui.');
    }

    public function destroy(Media $media)
    {
        Gate::authorize('manage-content');
        abort_if(Post::where('featured_image', $media->url)->orWhere('content', 'like', '%'.$media->path.'%')->exists(), 422, 'Gambar masih digunakan artikel.');
        File::delete(public_path($media->path));
        $media->delete();

        return back()->with('success', 'Gambar dihapus.');
    }
}
