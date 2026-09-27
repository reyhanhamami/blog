@extends('public.layout')
@section('seo_title', $lesson->title.' | '.$course->title)
@section('seo_description', $lesson->title.' dalam '.$course->title)
@section('content')
<nav class="mb-6 text-sm text-slate-500"><a wire:navigate href="{{ route('home') }}">Beranda</a> / <a wire:navigate href="{{ route('course.show', $course) }}">{{ $course->title }}</a> / {{ $lesson->title }}</nav>
<div class="mx-auto max-w-3xl">
<p class="text-sm text-indigo-700">{{ $completedCount }} / {{ $total }} selesai · {{ $total ? round($completedCount / $total * 100) : 0 }}%</p>
<div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-600" style="width:{{ $total ? round($completedCount / $total * 100) : 0 }}%"></div></div>
<h1 class="mt-7 text-4xl font-bold">{{ $lesson->title }}</h1>
@switch($lesson->type)
@case(\App\Enums\CourseLessonType::Quiz)
@if($lesson->quiz && $lesson->quiz->status === 'published')
<h2 class="mt-6 text-xl font-semibold">{{ $lesson->quiz->title }}</h2><p class="mt-2 text-slate-600">{{ $lesson->quiz->description }}</p>
<livewire:quiz-player :quiz="$lesson->quiz" :lesson="$lesson" />
@else<p class="mt-6 text-slate-500">Quiz belum tersedia.</p>@endif
@break
@case(\App\Enums\CourseLessonType::Article)
@if($lesson->post && $lesson->post->status === 'published' && $lesson->post->published_at?->isPast())<a wire:navigate href="{{ route('post.show', $lesson->post->slug) }}" class="btn-secondary mt-8">Baca {{ $lesson->post->title }}</a>@endif
@break
@case(\App\Enums\CourseLessonType::Video)
@if($lesson->video && $lesson->video->status === 'published' && $lesson->video->published_at?->isPast())<a wire:navigate href="{{ route('video.show', $lesson->video) }}" class="btn-secondary mt-8">Tonton {{ $lesson->video->title }}</a>@endif
@break
@default
<div class="article-content mt-8">{!! app(\App\Services\Content\HtmlSanitizer::class)->clean($lesson->content) !!}</div>
@endswitch
@if($lesson->type !== \App\Enums\CourseLessonType::Quiz)
@auth<form action="{{ route('reader.lesson.complete', [$course, $lesson]) }}" method="post" class="mt-8">@csrf<button class="btn-primary" @disabled($completed)>{{ $completed ? 'Sudah selesai' : 'Tandai selesai' }}</button></form>
@else<a wire:navigate href="{{ route('login') }}" class="btn-secondary mt-8">Masuk untuk menyimpan progres</a>@endauth
@elseif(!auth()->check())<p class="mt-5 text-sm text-slate-500">Masuk untuk menyimpan progres quiz ke kelas.</p>@endif
<div class="mt-10 flex justify-between gap-3">@if($previous)<a wire:navigate href="{{ route('course.lesson', [$course, $previous]) }}" class="btn-secondary">← {{ $previous->title }}</a>@else<span></span>@endif @if($next)<a wire:navigate href="{{ route('course.lesson', [$course, $next]) }}" class="btn-secondary">{{ $next->title }} →</a>@endif</div>
</div>
@endsection