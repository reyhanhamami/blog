@extends('public.layout')
@section('seo_title', $quiz->title.' | Besofton Insights')
@section('seo_description', $quiz->description ?: $quiz->title)
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">Kuis</span></nav>
    <header class="public-page-hero"><p class="public-kicker">UJI PEMAHAMAN</p><h1>{{ $quiz->title }}</h1>@if($quiz->description)<p>{{ $quiz->description }}</p>@endif</header>
    <div class="public-narrow"><livewire:quiz-player :quiz="$quiz" /></div>
</div>
@endsection
