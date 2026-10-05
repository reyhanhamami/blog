@extends('public.layout')
@section('main_class', 'insights-home')
@section('seo_title', 'Besofton Insights | Ide, Panduan, dan Strategi Digital')
@section('seo_description', $heroTagline)
@section('canonical', route('home'))
@if($primaryPost && ($primaryPost->featured_image || $defaultImage))@section('og_image', $primaryPost->featured_image ?: $defaultImage)@endif
@if($featuredPosts->count() > 1)
@push('head')
    @vite('resources/js/public-home.js')
@endpush
@endif
@section('content')
<section class="home-hero">
    <div class="home-container hero-grid">
        <div class="hero-copy">
            <p class="public-eyebrow hero-eyebrow">{{ $hero['eyebrow'] }} <span aria-hidden="true"></span></p>
            <h1>{{ $hero['heading_line_1'] }}<br>{{ $hero['heading_line_2'] }}<br><em>{{ $hero['heading_highlight'] }}</em></h1>
            <svg class="hero-arrow-doodle" aria-hidden="true" viewBox="0 0 200 70" fill="none"><path d="M185 9C132 2 86 26 18 54m0 0 29-2M18 54l19-22m143-9-17 27 29-14" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <p class="hero-description">{{ $hero['description'] }}</p>
            <form action="{{ route('search') }}" method="get" class="hero-search">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                <label class="sr-only" for="home-search">Cari topik atau artikel</label>
                <input id="home-search" type="search" name="q" placeholder="{{ $hero['search_placeholder'] }}" required>
                <button type="submit" aria-label="Cari artikel">→</button>
            </form>
        </div>
        <div class="hero-collage" aria-label="Sorotan Besofton Insights">
            <svg class="collage-gold-ring" aria-hidden="true" viewBox="0 0 410 410" fill="none"><path d="M365 72C275-14 100 8 44 118-12 225 56 365 165 391c130 31 227-70 222-174C381 95 271 10 155 31 66 47 10 120 26 216" stroke="currentColor" stroke-width="19" stroke-linecap="round" opacity=".82"/><path d="M370 85C286-2 109 18 54 117-8 230 75 367 184 385" stroke="currentColor" stroke-width="5" opacity=".55"/></svg>
            <div class="collage-paper"></div>
            <div class="collage-photo">
                @if($hero['image_url'])
                    <img src="{{ $hero['image_url'] }}" alt="{{ $hero['image_alt'] }}" width="1000" height="680" loading="eager" fetchpriority="high">
                @else
                    <div class="collage-photo-fallback"><span>BESOFTON</span><strong>Build<br>Grow<br><em>Together.</em></strong></div>
                @endif
            </div>
            <div class="collage-black-label">@foreach($hero['black_label_lines'] as $line){{ $line }}@if(! $loop->last)<br>@endif@endforeach</div>
            <div class="collage-gold-note">{{ $hero['gold_note'] }}</div>
            @if($hero['white_notes'])<div class="collage-white-note">@foreach($hero['white_notes'] as $note)<span>{{ $note }}</span>@endforeach</div>@endif
            <svg class="collage-black-scribble" aria-hidden="true" viewBox="0 0 100 120" fill="none"><path d="M4 105 85 18 41 40M82 13 98 5 91 28" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 117C25 83 45 97 67 61" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        </div>
    </div>
</section>

@if($popularCategories->isNotEmpty())
<section class="home-categories home-container" aria-labelledby="categories-title">
    <div class="home-section-heading"><h2 id="categories-title" class="public-section-title">Kategori Populer <span class="heading-scribble" aria-hidden="true"></span></h2><a wire:navigate href="{{ route('categories.index') }}">Lihat semua kategori <span aria-hidden="true">→</span></a></div>
    <nav class="category-chip-row" aria-label="Kategori populer">
        <a wire:navigate href="{{ route('home') }}" class="category-chip is-active" aria-current="page"><span class="chip-icon" aria-hidden="true">⊞</span>Semua</a>
        @foreach($popularCategories as $category)
            <a wire:navigate href="{{ route('category.show', $category) }}" class="category-chip"><span class="chip-icon" aria-hidden="true">◌</span>{{ $category->name }}</a>
        @endforeach
    </nav>
</section>
@endif

@if($featuredPosts->isNotEmpty())
<section class="home-featured home-container" aria-labelledby="featured-title">
    <h2 id="featured-title" class="sr-only">Artikel Unggulan</h2>
    <div class="featured-slider" data-featured-swiper>
        <div class="swiper-wrapper">
            @foreach($featuredPosts as $post)
                <div class="swiper-slide">@include('public.partials.home-featured', ['post' => $post, 'defaultImage' => $defaultImage])</div>
            @endforeach
        </div>
    </div>
    @if($featuredPosts->count() > 1)
    <div class="featured-controls"><button type="button" data-featured-prev aria-label="Artikel unggulan sebelumnya">←</button><button type="button" data-featured-next aria-label="Artikel unggulan berikutnya">→</button></div>
    @endif
</section>
@endif

<section class="home-latest home-container" aria-labelledby="latest-title">
    <div class="home-section-heading"><h2 id="latest-title" class="public-section-title">Artikel Terbaru <span class="heading-scribble is-dark" aria-hidden="true"></span></h2><a wire:navigate href="{{ route('search', ['type' => 'articles']) }}">Lihat semua artikel <span aria-hidden="true">→</span></a></div>
    @if($latestPosts->isNotEmpty())
        <div class="home-card-grid">@foreach($latestPosts as $post)@include('public.partials.home-card', ['post' => $post, 'defaultImage' => $defaultImage])@endforeach</div>
    @else
        <div class="home-empty-state"><p>Artikel terbaru sedang disiapkan.</p><a wire:navigate href="{{ route('search') }}">Jelajahi artikel →</a></div>
    @endif
</section>

@endsection
