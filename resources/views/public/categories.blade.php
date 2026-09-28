@extends('public.layout')
@section('seo_title', 'Kategori Artikel | Besofton Insights')
@section('seo_description', 'Jelajahi kategori artikel dan panduan Besofton Insights.')
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">Kategori</span></nav>
    <header class="public-page-hero"><p class="public-kicker">JELAJAHI WAWASAN</p><h1>Semua Kategori</h1><p>Temukan panduan dan sudut pandang baru sesuai bidang yang ingin Anda dalami.</p></header>
    <div class="public-card-grid public-section">@forelse($categories as $category)<a wire:navigate href="{{ route('category.show', $category) }}" class="public-card public-panel"><span class="public-badge">Kategori</span><h2>{{ $category->name }}</h2><p>{{ $category->published_posts_count }} artikel · Jelajahi →</p></a>@empty<div class="public-empty"><p>Kategori belum tersedia.</p><a wire:navigate href="{{ route('home') }}" class="public-outline-button">Kembali ke Insights</a></div>@endforelse</div>
    <div class="public-pagination">{{ $categories->links('public.partials.pagination') }}</div>
</div>
@endsection
