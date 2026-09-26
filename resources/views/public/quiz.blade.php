@extends('public.layout')
@section('seo_title', $quiz->title.' | Besofton Insights')
@section('seo_description', $quiz->description ?: $quiz->title)
@section('content')
<div class="mx-auto max-w-3xl"><nav class="mb-6 text-sm text-slate-500"><a wire:navigate href="{{ route('home') }}">Beranda</a> / Kuis</nav><h1 class="text-4xl font-bold">{{ $quiz->title }}</h1><p class="mt-4 text-slate-600">{{ $quiz->description }}</p><livewire:quiz-player :quiz="$quiz" /></div>
@endsection