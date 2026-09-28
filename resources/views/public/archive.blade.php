@extends('public.layout')
@section('seo_title', $title.' | Besofton Insights')
@section('seo_description', $description ?: $title.' di Besofton Insights')
@section('robots', $noindex ? 'noindex,follow' : 'index,follow')
@push('head')
@if(isset($entity) && $entity instanceof \App\Models\Author)
@php $authorSchema = ['@context'=>'https://schema.org','@type'=>'ProfilePage','mainEntity'=>['@type'=>'Person','name'=>$entity->name,'description'=>$entity->short_bio,'url'=>route('author.show', $entity)]]; @endphp
<script type="application/ld+json">{!! json_encode($authorSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endif
@endpush
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">{{ $title }}</span></nav>
    <header class="public-page-hero">
        <p class="public-kicker">{{ $search !== null ? 'TEMUKAN WAWASAN' : (isset($entity) && $entity instanceof \App\Models\Author ? 'PENULIS' : 'JELAJAHI INSIGHTS') }}</p>
        <h1>{{ $title }}</h1>
        @if($description)<p>{{ $description }}</p>@endif
        @if(isset($entity) && $entity instanceof \App\Models\Author)
            @if($entity->job_title)<p class="archive-author-role">{{ $entity->job_title }}</p>@endif
            @if($entity->full_bio)<p class="archive-author-bio">{{ $entity->full_bio }}</p>@endif
            <div class="public-inline-links">@foreach(['website' => 'Website', 'github_url' => 'GitHub', 'linkedin_url' => 'LinkedIn', 'youtube_url' => 'YouTube'] as $field => $label)@if($entity->$field)<a href="{{ $entity->$field }}" rel="noopener noreferrer" target="_blank">{{ $label }} ↗</a>@endif @endforeach</div>
        @endif
    </header>
    @if($search !== null)
    <form action="{{ route('search') }}" method="get" class="public-search-form" role="search">
        <label for="archive-search">Cari artikel, video, atau kelas</label>
        <div class="public-search-fields"><input id="archive-search" class="public-input" name="q" value="{{ $search }}" type="search" placeholder="Apa yang ingin Anda pelajari?"><label class="sr-only" for="archive-type">Jenis konten</label><select id="archive-type" name="type"><option value="all">Semua</option><option value="articles" @selected(($type ?? '') === 'articles')>Artikel</option><option value="videos" @selected(($type ?? '') === 'videos')>Video</option><option value="courses" @selected(($type ?? '') === 'courses')>Kelas</option></select><button class="public-button is-gold" type="submit">Cari →</button></div>
    </form>
    @endif
    @if(isset($entity) && $entity instanceof \App\Models\Topic)
        @php $relatedTopics = \App\Models\Topic::whereKeyNot($entity->id)->orderBy('name')->take(6)->get(); $topicPaths = \App\Models\LearningPath::where('status','published')->where('title','like','%'.$entity->name.'%')->take(3)->get(); @endphp
        @if($relatedTopics->isNotEmpty())<nav class="public-chip-row public-section" aria-label="Topik terkait">@foreach($relatedTopics as $relatedTopic)<a wire:navigate href="{{ route('topic.show', $relatedTopic) }}" class="public-chip">{{ $relatedTopic->name }}</a>@endforeach</nav>@endif
        @if($topicPaths->isNotEmpty())<section class="public-section"><h2 class="public-section-heading">Jalur belajar</h2><div class="public-card-grid">@foreach($topicPaths as $path)<a wire:navigate href="{{ route('path.show', $path) }}" class="public-card public-panel"><span class="public-badge">Jalur belajar</span><h3>{{ $path->title }}</h3><p>{{ $path->description }}</p></a>@endforeach</div></section>@endif
    @endif
    @if(!isset($type) || in_array($type, ['all','articles']))
    <section class="public-section"><h2 class="public-section-heading">{{ $search !== null ? 'Artikel' : 'Artikel dalam koleksi ini' }}</h2><div class="public-card-grid">@forelse($posts as $post)@include('public.partials.card', ['post'=>$post])@empty<div class="public-empty"><p>Belum ada artikel yang sesuai.</p><a wire:navigate href="{{ route('home') }}" class="public-outline-button">Jelajahi Insights</a></div>@endforelse</div><div class="public-pagination">{{ $posts->links('public.partials.pagination') }}</div></section>
    @endif
    @if(isset($videos) && $videos->isNotEmpty())<section class="public-section"><h2 class="public-section-heading">Video</h2><div class="public-card-grid">@foreach($videos as $video)<a wire:navigate href="{{ route('video.show', $video) }}" class="public-card public-panel"><span class="public-badge">Video</span><h3>{{ $video->title }}</h3><p>{{ $video->description }}</p></a>@endforeach</div></section>@endif
    @if(isset($courses) && $courses->isNotEmpty())<section class="public-section"><h2 class="public-section-heading">Kelas</h2><div class="public-card-grid">@foreach($courses as $course)<a wire:navigate href="{{ route('course.show', $course) }}" class="public-card public-panel"><span class="public-badge">Kelas</span><h3>{{ $course->title }}</h3><p>{{ $course->description }}</p></a>@endforeach</div></section>@endif
</div>
@endsection
