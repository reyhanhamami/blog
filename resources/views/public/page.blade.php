@extends('public.layout')
@section('seo_title', $page->title.' | Besofton Insights')
@section('seo_description', \Illuminate\Support\Str::limit(strip_tags($page->content), 155))
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">{{ $page->title }}</span></nav>
    <header class="public-page-hero"><p class="public-kicker">BESOFTON INSIGHTS</p><h1>{{ $page->title }}</h1></header>
    <article class="public-narrow article-content prose-besofton">{!! $page->content !!}</article>
</div>
@endsection
