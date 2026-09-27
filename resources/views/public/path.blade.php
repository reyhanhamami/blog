@extends('public.layout')
@section('seo_title', $path->title.' | Besofton Insights')
@section('seo_description', $path->description ?: $path->title)
@section('content')
<nav class="mb-6 text-sm text-slate-500"><a wire:navigate href="{{ route('home') }}">Beranda</a> / Jalur belajar</nav><h1 class="text-4xl font-bold">{{ $path->title }}</h1><p class="mt-4 max-w-3xl text-slate-600">{{ $path->description }}</p>
<ol class="mt-9 max-w-3xl space-y-3">
@forelse($path->items as $item)
@php
$link = match($item->type) {
    \App\Enums\LearningPathItemType::Article => $item->post && $item->post->status === 'published' && $item->post->published_at?->isPast() ? route('post.show', $item->post->slug) : null,
    \App\Enums\LearningPathItemType::Video => $item->video && $item->video->status === 'published' && $item->video->published_at?->isPast() ? route('video.show', $item->video) : null,
    \App\Enums\LearningPathItemType::Quiz => $item->quiz && $item->quiz->status === 'published' ? route('quiz.show', $item->quiz) : null,
};
$title = $item->post?->title ?? $item->video?->title ?? $item->quiz?->title;
@endphp
@if($link)<li><a wire:navigate href="{{ $link }}" class="block rounded-xl border border-slate-200 p-5 hover:border-indigo-500"><span class="mr-3 text-indigo-600">{{ $loop->iteration }}.</span>{{ $title }}</a></li>@endif
@empty<li class="text-slate-500">Materi akan segera hadir.</li>@endforelse
</ol>
@endsection