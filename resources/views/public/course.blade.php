@extends('public.layout')
@section('seo_title', $course->title.' | Besofton Insights')
@section('seo_description', $course->description ?: $course->title)
@section('content')
<nav class="mb-6 text-sm text-slate-500"><a wire:navigate href="{{ route('home') }}">Beranda</a> / Kelas</nav><h1 class="text-4xl font-bold">{{ $course->title }}</h1><p class="mt-4 max-w-3xl text-slate-600">{{ $course->description }}</p><div class="mt-9 max-w-3xl space-y-5">@forelse($course->modules as $module)<section class="card"><h2 class="text-xl font-bold">{{ $module->title }}</h2><ol class="mt-4 space-y-2">@foreach($module->lessons as $lesson)<li><a wire:navigate href="{{ route('course.lesson', [$course, $lesson]) }}" class="block rounded-lg border border-slate-200 p-3 hover:border-indigo-500">{{ $loop->iteration }}. {{ $lesson->title }} →</a></li>@endforeach</ol></section>@empty<p class="text-slate-500">Materi kelas akan segera hadir.</p>@endforelse</div>
@endsection