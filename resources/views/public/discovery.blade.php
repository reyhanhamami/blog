@extends('public.layout')
@section('seo_title', $title.' | Besofton Insights')
@section('seo_description', $description)
@section('canonical', url()->current())
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">{{ $title }}</span></nav>
    <header class="public-page-hero"><p class="public-kicker">BESOFTON INSIGHTS</p><h1>{{ $title }}</h1><p>{{ $description }}</p></header>
    <section class="public-section" aria-label="{{ $title }}">
        <div class="public-card-grid">
            @forelse($items as $item)
                @switch($kind)
                    @case('topics')
                        <a wire:navigate href="{{ route('topic.show', $item) }}" class="public-card public-panel discovery-card">
                            <span class="public-badge">Topik</span><h2>{{ $item->name }}</h2>
                            @if($item->description)<p>{{ $item->description }}</p>@endif
                            <span class="discovery-card-bottom">{{ $item->published_posts_count }} artikel <span aria-hidden="true">→</span></span>
                        </a>
                        @break
                    @case('paths')
                        <a wire:navigate href="{{ route('path.show', $item) }}" class="public-card public-panel discovery-card">
                            <span class="public-badge">Jalur belajar</span><h2>{{ $item->title }}</h2>
                            @if($item->description)<p>{{ $item->description }}</p>@endif
                            <span class="discovery-card-bottom">{{ $item->items_count }} materi <span>Mulai →</span></span>
                        </a>
                        @break
                    @case('courses')
                        @php
                            $totalLessons = (int) ($lessonCounts[$item->id] ?? 0);
                            $completedLessons = (int) ($completedCounts[$item->id] ?? 0);
                            $progress = $totalLessons > 0 ? min(100, round($completedLessons / $totalLessons * 100)) : 0;
                            $lastLessonId = $lastLessons[$item->id] ?? null;
                            $nextLessonId = $nextLessons[$item->id] ?? null;
                        @endphp
                        <article class="public-card public-panel discovery-card">
                            <span class="public-badge">Kelas</span><h2><a wire:navigate href="{{ route('course.show', $item) }}">{{ $item->title }}</a></h2>
                            @if($item->description)<p>{{ $item->description }}</p>@endif
                            <p class="discovery-meta">{{ $item->modules_count }} modul · {{ $totalLessons }} pelajaran</p>
                            @auth
                                @if($totalLessons > 0 && $completedLessons > 0)<div class="public-progress" role="progressbar" aria-label="Progres {{ $item->title }}" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $progress }}%"></span></div><p class="discovery-progress-label">{{ $completedLessons }} / {{ $totalLessons }} selesai · {{ $progress }}%</p>@endif
                            @endauth
                            <div class="discovery-card-bottom">
                                @if($totalLessons > 0 && $completedLessons >= $totalLessons)<a wire:navigate href="{{ route('course.show', $item) }}">Kelas selesai →</a>
                                @elseif($nextLessonId && ($lastLessonId || $completedLessons > 0))<a wire:navigate href="{{ route('course.lesson', [$item, $nextLessonId]) }}">Lanjutkan →</a>
                                @else<a wire:navigate href="{{ route('course.show', $item) }}">Mulai →</a>@endif
                            </div>
                        </article>
                        @break
                    @case('videos')
                        <article class="public-card discovery-card">
                            <a wire:navigate href="{{ route('video.show', $item) }}" class="public-card-media" aria-label="Tonton {{ $item->title }}"><img src="https://i.ytimg.com/vi/{{ $item->youtube_id }}/hqdefault.jpg" alt="Pratinjau video {{ $item->title }}" width="640" height="360" loading="lazy"></a>
                            <div class="public-card-body"><span class="public-badge">Video</span><h2><a wire:navigate href="{{ route('video.show', $item) }}">{{ $item->title }}</a></h2>@if($item->description)<p>{{ $item->description }}</p>@endif<span class="discovery-card-bottom"><time datetime="{{ $item->published_at?->toDateString() }}">{{ $item->published_at?->timezone('Asia/Jakarta')->format('d M Y') }}</time><a wire:navigate href="{{ route('video.show', $item) }}" aria-label="Tonton {{ $item->title }}">Tonton →</a></span></div>
                        </article>
                        @break
                @endswitch
            @empty
                <div class="public-empty"><p>Konten belum tersedia. Jelajahi artikel Besofton Insights sambil menunggu materi baru.</p><a wire:navigate href="{{ route('home') }}" class="public-outline-button">Ke Insights</a></div>
            @endforelse
        </div>
        <div class="public-pagination">{{ $items->links('public.partials.pagination') }}</div>
    </section>
</div>
@endsection
