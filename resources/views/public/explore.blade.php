@extends('public.layout')
@section('seo_title', $context === 'category' ? $currentCategory->name.' | Besofton Insights' : 'Cari Insight | Besofton Insights')
@section('seo_description', $context === 'category' ? ($currentCategory->description ?: 'Artikel tentang '.$currentCategory->name.' di Besofton Insights.') : 'Cari artikel, tutorial, dan materi belajar dari Besofton Insights.')
@section('canonical', $context === 'category' ? route('category.show', $currentCategory) : route('search'))
@section('robots', $context === 'category' ? 'index,follow' : 'noindex,follow')
@push('head')
@if($context === 'category')
    @php $breadcrumbSchema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => route('home')], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Kategori', 'item' => route('categories.index')], ['@type' => 'ListItem', 'position' => 3, 'name' => $currentCategory->name, 'item' => route('category.show', $currentCategory)]]]; @endphp
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endif
@endpush
@section('content')
<div class="public-shell discovery-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb">
        <a wire:navigate href="{{ route('home') }}">Beranda</a><span aria-hidden="true">/</span>
        @if($context === 'category')<a wire:navigate href="{{ route('categories.index') }}">Kategori</a><span aria-hidden="true">/</span><span aria-current="page">{{ $currentCategory->name }}</span>
        @else<span aria-current="page">Cari</span>@endif
    </nav>
    <header class="public-page-hero discovery-hero">
        <p class="public-kicker">{{ $context === 'category' ? 'JELAJAHI INSIGHTS' : 'CARI INSIGHT' }}</p>
        <h1>{{ $context === 'category' ? $currentCategory->name : 'Temukan insight yang Anda butuhkan.' }}</h1>
        @if($context === 'category')
            @if($currentCategory->description)<p>{{ $currentCategory->description }}</p>@endif
            @php $categoryTotal = $categories->firstWhere('id', $currentCategory->id)?->published_posts_count; @endphp
            @if($categoryTotal !== null)<span class="discovery-hero-count">{{ $categoryTotal }} artikel diterbitkan</span>@endif
        @else<p>Jelajahi artikel, tutorial, video, dan materi belajar dari Besofton.</p>@endif
    </header>

    <nav class="discovery-category-row" aria-label="Pilih kategori">
        <a wire:navigate href="{{ route('search') }}" class="public-chip {{ $context === 'search' && ! $filters['category'] ? 'is-active' : '' }}" @if($context === 'search' && ! $filters['category']) aria-current="page" @endif>Semua</a>
        @foreach($categories as $category)
            <a wire:navigate href="{{ route('category.show', $category) }}" class="public-chip {{ ($currentCategory ?? $filters['category'])?->id === $category->id ? 'is-active' : '' }}" @if(($currentCategory ?? $filters['category'])?->id === $category->id) aria-current="page" @endif>{{ $category->name }}</a>
        @endforeach
    </nav>

    @if($context === 'search' && ($filters['query'] !== '' || $resultType !== 'all'))
        <nav class="discovery-result-tabs" aria-label="Jenis hasil pencarian">
            @foreach(['all' => 'Semua', 'article' => 'Artikel', 'video' => 'Video', 'learning' => 'Belajar', 'course' => 'Kelas'] as $tab => $label)
                @php $tabParams = in_array($tab, ['all', 'article'], true) ? request()->except(['page', 'type']) : ['q' => $filters['query']]; $tabParams['type'] = $tab; @endphp
                <a wire:navigate href="{{ route('search', array_filter($tabParams, fn ($value) => $value !== '')) }}" class="{{ $resultType === $tab ? 'is-active' : '' }}" @if($resultType === $tab) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    @endif

    <form action="{{ $context === 'category' ? route('category.show', $currentCategory) : route('search') }}" method="get" role="search" class="public-search-form discovery-toolbar" data-discovery-form>
        @if($context === 'search' && $resultType !== 'all')<input type="hidden" name="type" value="{{ $resultType }}">@endif
        <div class="discovery-search-field">
            <label for="discovery-q">{{ $context === 'category' ? 'Cari di kategori ini' : 'Cari insight' }}</label>
            <input id="discovery-q" class="public-input" type="search" name="q" value="{{ $filters['query'] }}" placeholder="{{ $context === 'category' ? 'Cari di kategori ini...' : 'Cari artikel, topik, video, atau kelas...' }}" autocomplete="off">
        </div>
        @if($context === 'search' && in_array($resultType, ['all', 'article'], true))
            <div class="discovery-field"><label for="discovery-category">Kategori</label><select id="discovery-category" name="category"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected($filters['category']?->id === $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        @endif
        @if($context === 'category' || in_array($resultType, ['all', 'article'], true))
            <div class="discovery-field"><label for="discovery-topic">Topik</label><select id="discovery-topic" name="topic"><option value="">Semua topik</option>@foreach($topics as $topic)<option value="{{ $topic->slug }}" @selected($filters['topic']?->id === $topic->id)>{{ $topic->name }}</option>@endforeach</select></div>
            <div class="discovery-field"><label for="discovery-type">Tipe artikel</label><select id="discovery-type" name="{{ $context === 'category' ? 'type' : 'content_type' }}"><option value="">Semua tipe</option>@foreach($contentTypes as $value => $label)<option value="{{ $value }}" @selected($filters['contentType'] === $value)>{{ $label }}</option>@endforeach</select></div>
            @if($difficulties)<div class="discovery-field"><label for="discovery-difficulty">Tingkat</label><select id="discovery-difficulty" name="difficulty"><option value="">Semua tingkat</option>@foreach($difficulties as $value => $label)<option value="{{ $value }}" @selected($filters['difficulty'] === $value)>{{ $label }}</option>@endforeach</select></div>@endif
            <div class="discovery-field"><label for="discovery-sort">Urutkan</label><select id="discovery-sort" name="sort">@foreach(\App\Services\ContentDiscoveryService::SORTS as $value => $label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach</select></div>
        @endif
        <button type="submit" class="public-button is-gold discovery-submit">Terapkan</button>
    </form>

    @php
        $resetUrl = $context === 'category' ? route('category.show', $currentCategory) : route('search');
        $activeFilters = [];
        if ($filters['query'] !== '') $activeFilters['q'] = '“'.$filters['query'].'”';
        if ($context === 'search' && $filters['category']) $activeFilters['category'] = $filters['category']->name;
        if ($filters['topic']) $activeFilters['topic'] = $filters['topic']->name;
        if ($filters['contentType'] !== '') $activeFilters[$context === 'category' ? 'type' : 'content_type'] = $contentTypes[$filters['contentType']] ?? $filters['contentType'];
        if ($filters['difficulty'] !== '') $activeFilters['difficulty'] = $difficulties[$filters['difficulty']] ?? $filters['difficulty'];
        if ($filters['sort'] !== 'latest') $activeFilters['sort'] = \App\Services\ContentDiscoveryService::SORTS[$filters['sort']];
    @endphp
    @if($activeFilters)
        <div class="discovery-active-filters" aria-label="Filter aktif">
            @foreach($activeFilters as $key => $label)<a wire:navigate href="{{ request()->fullUrlWithQuery([$key => null, 'page' => null]) }}" class="discovery-filter-chip" aria-label="Hapus filter {{ $label }}">{{ $label }} <span aria-hidden="true">×</span></a>@endforeach
            <a wire:navigate href="{{ $resetUrl }}" class="discovery-reset">Reset filter</a>
        </div>
    @endif

    @if($context === 'category' || $resultType === 'article' || ($resultType === 'all' && ($posts->total() > 0 || ($videos->isEmpty() && $courses->isEmpty() && $paths->isEmpty()))))
        <section id="discovery-results" class="discovery-results" aria-labelledby="discovery-results-title">
            <div class="discovery-results-heading"><div><p class="public-kicker">HASIL PENELUSURAN</p><h2 id="discovery-results-title">{{ $posts->total() }} artikel ditemukan</h2>@if($filters['query'] !== '')<p>Hasil untuk “{{ $filters['query'] }}”</p>@endif</div><span class="discovery-loading" data-discovery-loading hidden role="status">Memuat hasil...</span></div>
            @if($posts->isNotEmpty())
                <div class="public-card-grid discovery-article-grid {{ $posts->count() === 1 ? 'is-single' : ($posts->count() === 2 ? 'is-double' : '') }}">
                    @foreach($posts as $post)@include('public.partials.card', ['post' => $post])@endforeach
                </div>
                <div class="public-pagination">{{ $posts->links('public.partials.pagination') }}</div>
            @elseif($context === 'category' || ($videos->isEmpty() && $courses->isEmpty() && $paths->isEmpty()))
                <div class="public-empty discovery-empty"><h3>Tidak menemukan insight yang cocok.</h3><p>Coba ubah kata kunci atau hapus beberapa filter.</p><a wire:navigate href="{{ $resetUrl }}" class="public-outline-button">Reset filter</a></div>
            @endif
        </section>
    @endif

    @if($context === 'search')
        @foreach(['video' => ['Video', $videos, 'video.show'], 'learning' => ['Jalur belajar', $paths, 'path.show'], 'course' => ['Kelas', $courses, 'course.show']] as $kind => [$heading, $results, $routeName])
            @if(in_array($resultType, ['all', $kind], true) && $results->isNotEmpty())
                <section class="public-section discovery-other-results"><h2 class="public-section-heading">{{ $heading }}</h2><div class="public-card-grid">@foreach($results as $item)<a wire:navigate href="{{ route($routeName, $item) }}" class="public-card public-panel discovery-card"><span class="public-badge">{{ $heading }}</span><h3>{{ $item->title }}</h3>@if($item->description)<p>{{ $item->description }}</p>@endif<span class="discovery-card-bottom">Jelajahi <span aria-hidden="true">→</span></span></a>@endforeach</div>@if($results instanceof \Illuminate\Pagination\LengthAwarePaginator)<div class="public-pagination">{{ $results->links('public.partials.pagination') }}</div>@endif</section>
            @endif
        @endforeach
        @if(! in_array($resultType, ['all', 'article'], true) && $videos->isEmpty() && $courses->isEmpty() && $paths->isEmpty())<div class="public-empty discovery-empty"><h2>Tidak menemukan insight yang cocok.</h2><p>Coba kata kunci lain atau jelajahi artikel kami.</p><a wire:navigate href="{{ route('search') }}" class="public-outline-button">Reset filter</a></div>@endif
    @endif
</div>
@endsection
