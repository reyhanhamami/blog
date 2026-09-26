@extends('public.layout')
@section('seo_title', $path->title.' | Besofton Insights')
@section('seo_description', $path->description ?: $path->title)
@section('content')
<nav class="mb-6 text-sm text-slate-500"><a wire:navigate href="{{ route('home') }}">Beranda</a> / Jalur belajar</nav><h1 class="text-4xl font-bold">{{ $path->title }}</h1><p class="mt-4 max-w-3xl text-slate-600">{{ $path->description }}</p><ol class="mt-9 max-w-3xl space-y-3">@forelse($path->items as $item)@if($item->post && $item->post->status === 'published' && $item->post->published_at?->isPast())<li><a wire:navigate href="{{ route('post.show', $item->post->slug) }}" class="block rounded-xl border border-slate-200 p-5 hover:border-indigo-500"><span class="mr-3 text-indigo-600">{{ $loop->iteration }}.</span>{{ $item->post->title }}</a></li>@endif @empty<li class="text-slate-500">Materi akan segera hadir.</li>@endforelse</ol>
@endsection