@extends('public.layout')
@section('seo_title', $page->title.' | Besofton Insights')
@section('seo_description', \Illuminate\Support\Str::limit(strip_tags($page->content), 155))
@section('content')
<nav class="mb-6 text-sm text-slate-500"><a wire:navigate href="{{ route('home') }}">Beranda</a> / {{ $page->title }}</nav><article class="mx-auto max-w-3xl"><h1 class="text-4xl font-bold">{{ $page->title }}</h1><div class="article-content mt-8">{!! $page->content !!}</div></article>
@endsection