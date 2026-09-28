@extends('public.layout')
@section('seo_title', $lesson->title.' | '.$course->title)
@section('seo_description', $lesson->title.' dalam '.$course->title)
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><a wire:navigate href="{{ route('course.show', $course) }}">{{ $course->title }}</a><span>/</span><span aria-current="page">{{ $lesson->title }}</span></nav>
    <div class="public-narrow"><header class="public-page-hero"><p class="public-kicker">PELAJARAN</p><h1>{{ $lesson->title }}</h1><p>{{ $completedCount }} / {{ $total }} selesai · {{ $total ? round($completedCount / $total * 100) : 0 }}%</p><div role="progressbar" aria-label="Progres kelas" aria-valuenow="{{ $total ? round($completedCount / $total * 100) : 0 }}" aria-valuemin="0" aria-valuemax="100" class="public-progress"><span style="width:{{ $total ? round($completedCount / $total * 100) : 0 }}%"></span></div></header>
    <div class="public-section">
    @switch($lesson->type)
    @case(\App\Enums\CourseLessonType::Quiz)
        @if($lesson->quiz && $lesson->quiz->status === 'published')<div class="public-panel"><span class="public-badge">Kuis</span><h2 class="public-section-heading">{{ $lesson->quiz->title }}</h2><p>{{ $lesson->quiz->description }}</p></div><livewire:quiz-player :quiz="$lesson->quiz" :lesson="$lesson" />@else<div class="public-empty"><p>Kuis belum tersedia.</p></div>@endif
        @break
    @case(\App\Enums\CourseLessonType::Article)
        @if($lesson->post && $lesson->post->status === 'published' && $lesson->post->published_at?->isPast())<div class="public-panel"><p>Baca materi artikel untuk melanjutkan pelajaran ini.</p><a wire:navigate href="{{ route('post.show', $lesson->post->slug) }}" class="public-button is-gold">Baca {{ $lesson->post->title }} →</a></div>@endif
        @break
    @case(\App\Enums\CourseLessonType::Video)
        @if($lesson->video && $lesson->video->status === 'published' && $lesson->video->published_at?->isPast())<div class="public-panel"><p>Tonton video untuk melanjutkan pelajaran ini.</p><a wire:navigate href="{{ route('video.show', $lesson->video) }}" class="public-button is-gold">Tonton {{ $lesson->video->title }} →</a></div>@endif
        @break
    @default
        <div class="article-content prose-besofton">{!! app(\App\Services\Content\HtmlSanitizer::class)->clean($lesson->content) !!}</div>
    @endswitch
    @if($lesson->type !== \App\Enums\CourseLessonType::Quiz)
        @auth<form action="{{ route('reader.lesson.complete', [$course, $lesson]) }}" method="post" class="public-section">@csrf<button class="public-button" @disabled($completed)>{{ $completed ? 'Sudah selesai' : 'Tandai selesai' }}</button></form>
        @else<a wire:navigate href="{{ route('login') }}" class="public-outline-button public-section">Masuk untuk menyimpan progres</a>@endauth
    @elseif(!auth()->check())<p class="public-helper">Masuk untuk menyimpan progres kuis ke kelas.</p>@endif
    <nav aria-label="Navigasi pelajaran" class="article-adjacent">@if($previous)<a wire:navigate href="{{ route('course.lesson', [$course, $previous]) }}"><span>← Sebelumnya</span><strong>{{ $previous->title }}</strong></a>@endif @if($next)<a wire:navigate href="{{ route('course.lesson', [$course, $next]) }}"><span>Berikutnya →</span><strong>{{ $next->title }}</strong></a>@endif</nav>
    </div></div>
</div>
@endsection
