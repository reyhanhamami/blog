@extends('public.layout')
@section('seo_title', $video->title.' | Besofton Insights')
@section('seo_description', $video->description ?: $video->title)
@push('head')
@php $videoSchema = ['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => $video->title, 'description' => $video->description ?: $video->title, 'thumbnailUrl' => 'https://i.ytimg.com/vi/'.$video->youtube_id.'/hqdefault.jpg', 'uploadDate' => $video->published_at?->toAtomString(), 'embedUrl' => 'https://www.youtube-nocookie.com/embed/'.$video->youtube_id]; @endphp
<script type="application/ld+json">{!! json_encode($videoSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">Video</span></nav>
    <header class="public-page-hero"><p class="public-kicker">BELAJAR LEWAT VIDEO</p><h1>{{ $video->title }}</h1>@if($video->description)<p>{{ $video->description }}</p>@endif</header>
    <div class="public-video-frame" data-youtube="{{ $video->youtube_id }}"><img src="https://i.ytimg.com/vi/{{ $video->youtube_id }}/hqdefault.jpg" alt="Cuplikan video {{ $video->title }}" loading="lazy" width="1280" height="720"><button type="button" aria-label="Putar video {{ $video->title }}">▶ <span>Putar video</span></button></div>
</div>
@endsection
