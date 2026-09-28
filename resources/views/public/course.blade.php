@extends('public.layout')
@section('seo_title', $course->title.' | Besofton Insights')
@section('seo_description', $course->description ?: $course->title)
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">Kelas</span></nav>
    <header class="public-page-hero"><p class="public-kicker">BELAJAR TERSTRUKTUR</p><h1>{{ $course->title }}</h1>@if($course->description)<p>{{ $course->description }}</p>@endif</header>
    <div class="public-course-layout public-section"><div><h2 class="public-section-heading">Materi kelas</h2>@forelse($course->modules as $module)<section class="public-panel public-module"><p class="public-kicker">MODUL {{ sprintf('%02d', $loop->iteration) }}</p><h3>{{ $module->title }}</h3><ol class="public-step-list">@foreach($module->lessons as $lesson)<li><a wire:navigate href="{{ route('course.lesson', [$course, $lesson]) }}" class="public-step"><span class="public-step-number">{{ sprintf('%02d', $loop->iteration) }}</span><span><small>{{ ucfirst($lesson->type->value) }}</small><strong>{{ $lesson->title }}</strong></span><span aria-hidden="true">→</span></a></li>@endforeach</ol></section>@empty<div class="public-empty"><p>Materi kelas akan segera hadir.</p></div>@endforelse</div><aside class="public-panel public-course-aside"><span class="public-badge">Kelas</span><h2>Mulai dari yang paling dasar.</h2><p>Ikuti materi secara berurutan dan simpan progres belajar Anda.</p>@if($course->modules->first()?->lessons->first())<a wire:navigate href="{{ route('course.lesson', [$course, $course->modules->first()->lessons->first()]) }}" class="public-button is-gold">Mulai belajar →</a>@endif</aside></div>
</div>
@endsection
