@extends('public.layout')
@section('seo_title', $video->title.' | Besofton Insights')
@section('seo_description', $video->description ?: $video->title)
@push('head')
@php $videoSchema = ['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => $video->title, 'description' => $video->description ?: $video->title, 'thumbnailUrl' => 'https://i.ytimg.com/vi/'.$video->youtube_id.'/hqdefault.jpg', 'uploadDate' => $video->published_at?->toAtomString(), 'embedUrl' => 'https://www.youtube-nocookie.com/embed/'.$video->youtube_id]; @endphp
<script type="application/ld+json">{!! json_encode($videoSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
<nav class="mb-6 text-sm text-slate-500"><a wire:navigate href="{{ route('home') }}">Beranda</a> / Video</nav><article class="mx-auto max-w-4xl"><h1 class="text-4xl font-bold">{{ $video->title }}</h1><p class="mt-4 text-slate-600">{{ $video->description }}</p><div class="mt-8 aspect-video overflow-hidden rounded-2xl bg-slate-950"><iframe src="https://www.youtube-nocookie.com/embed/{{ $video->youtube_id }}" title="{{ $video->title }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen class="h-full w-full"></iframe></div></article>
@endsection