@extends('admin.layout')
@section('title', $lesson->exists ? 'Edit Pelajaran' : 'Buat Pelajaran')
@section('content')
<a wire:navigate href="{{ route('admin.courses.content.index', $course) }}" class="text-sm text-indigo-700">← Kembali</a>
<h1 class="my-6 text-3xl font-bold">{{ $lesson->exists ? 'Edit' : 'Buat' }} pelajaran · {{ $module->title }}</h1>
@php $type = old('type', $lesson->type?->value ?? \App\Enums\CourseLessonType::Custom->value); @endphp
<form method="post" action="{{ $lesson->exists ? route('admin.courses.lessons.update', [$course, $module, $lesson]) : route('admin.courses.lessons.store', [$course, $module]) }}" class="card max-w-4xl space-y-5" x-data="{ type: @js($type) }">
@csrf @if($lesson->exists) @method('PATCH') @endif
<div><label class="form-label">Judul *</label><input class="form-input" name="title" value="{{ old('title', $lesson->title) }}" required>@error('title')<p class="error">{{ $message }}</p>@enderror</div>
<div><label class="form-label">Urutan *</label><input class="form-input" name="sort_order" type="number" min="0" value="{{ old('sort_order', $lesson->sort_order ?? 0) }}" required></div>
<div><label class="form-label">Jenis pelajaran</label><select class="form-input" name="type" x-model="type">@foreach(\App\Enums\CourseLessonType::cases() as $option)<option value="{{ $option->value }}">{{ match($option) { \App\Enums\CourseLessonType::Custom => 'Konten sendiri', \App\Enums\CourseLessonType::Article => 'Artikel', \App\Enums\CourseLessonType::Video => 'Video', \App\Enums\CourseLessonType::Quiz => 'Quiz' } }}</option>@endforeach</select></div>
<div x-show="type === @js(\App\Enums\CourseLessonType::Custom->value)"><label class="form-label">Konten HTML</label><textarea class="form-input font-mono" name="content" rows="12">{{ old('content', $lesson->content) }}</textarea><p class="text-xs text-slate-500">HTML dibersihkan saat disimpan.</p></div>
<div x-show="type === @js(\App\Enums\CourseLessonType::Article->value)"><label class="form-label">Artikel</label><select class="form-input" data-search-select name="post_id"><option value="">Pilih artikel</option>@foreach($posts as $post)<option value="{{ $post->id }}" @selected(old('post_id', $lesson->post_id) == $post->id)>{{ $post->title }}</option>@endforeach</select></div>
<div x-show="type === @js(\App\Enums\CourseLessonType::Video->value)"><label class="form-label">Video</label><select class="form-input" data-search-select name="video_id"><option value="">Pilih video</option>@foreach($videos as $video)<option value="{{ $video->id }}" @selected(old('video_id', $lesson->video_id) == $video->id)>{{ $video->title }}</option>@endforeach</select></div>
<div x-show="type === @js(\App\Enums\CourseLessonType::Quiz->value)"><label class="form-label">Quiz</label><select class="form-input" data-search-select name="quiz_id"><option value="">Pilih quiz</option>@foreach($quizzes as $quiz)<option value="{{ $quiz->id }}" @selected(old('quiz_id', $lesson->quiz_id) == $quiz->id)>{{ $quiz->title }}</option>@endforeach</select>@error('quiz_id')<p class="error">{{ $message }}</p>@enderror<label class="mt-3 flex gap-2"><input type="checkbox" name="requires_pass" value="1" @checked(old('requires_pass', $lesson->requires_pass))> Harus lulus untuk menyelesaikan pelajaran</label></div>
<button class="btn-primary">Simpan pelajaran</button>
</form>
@endsection