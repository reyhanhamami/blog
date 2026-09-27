@extends('public.layout')
@section('seo_title', 'Kategori Artikel | Besofton Insights')
@section('seo_description', 'Jelajahi kategori artikel dan panduan Besofton Insights.')
@section('content')
<section class="mx-auto max-w-7xl px-5 py-12 md:px-8 md:py-16">
    <p class="public-eyebrow">JELAJAHI WAWASAN</p>
    <h1 class="public-section-title mt-3">Semua Kategori</h1>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($categories as $category)
            <a wire:navigate href="{{ route('category.show', $category) }}" class="rounded-xl border border-stone-200 bg-white p-6 transition hover:border-amber-500">
                <h2 class="text-xl font-bold">{{ $category->name }}</h2>
                <p class="mt-2 text-sm text-stone-600">{{ $category->published_posts_count }} artikel</p>
            </a>
        @empty
            <p class="text-stone-600">Kategori belum tersedia.</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $categories->links() }}</div>
</section>
@endsection