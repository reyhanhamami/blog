@extends('public.layout')
@section('seo_title', $path->title.' | Besofton Insights')
@section('seo_description', $path->description ?: $path->title)
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">Jalur belajar</span></nav>
    <header class="public-page-hero"><p class="public-kicker">JALUR BELAJAR</p><h1>{{ $path->title }}</h1>@if($path->description)<p>{{ $path->description }}</p>@endif</header>
    <section class="public-section public-narrow"><h2 class="public-section-heading">Langkah belajar</h2><ol class="public-step-list">
    @forelse($path->items as $item)
    @php
    $link = match($item->type) {
        \App\Enums\LearningPathItemType::Article => $item->post && $item->post->status === 'published' && $item->post->published_at?->isPast() ? route('post.show', $item->post->slug) : null,
        \App\Enums\LearningPathItemType::Video => $item->video && $item->video->status === 'published' && $item->video->published_at?->isPast() ? route('video.show', $item->video) : null,
        \App\Enums\LearningPathItemType::Quiz => $item->quiz && $item->quiz->status === 'published' ? route('quiz.show', $item->quiz) : null,
    };
    $title = $item->post?->title ?? $item->video?->title ?? $item->quiz?->title;
    @endphp
    @if($link)<li><a wire:navigate href="{{ $link }}" class="public-step"><span class="public-step-number">{{ sprintf('%02d', $loop->iteration) }}</span><span><small>{{ $item->type->value === 'quiz' ? 'Kuis' : ($item->type->value === 'video' ? 'Video' : 'Artikel') }}</small><strong>{{ $title }}</strong></span><span aria-hidden="true">→</span></a></li>@endif
    @empty<li class="public-empty"><p>Materi akan segera hadir.</p></li>@endforelse
    </ol></section>
</div>
@endsection
