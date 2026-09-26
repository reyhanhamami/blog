@extends('public.layout')
@section('seo_title', 'Halaman tidak ditemukan | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="mx-auto max-w-2xl py-20 text-center"><p class="text-7xl font-black text-indigo-700">404</p><h1 class="mt-5 text-3xl font-bold">Halaman tidak ditemukan</h1><p class="mt-3 text-slate-600">Coba cari artikel lain atau kembali ke beranda.</p><form action="{{ route('search') }}" class="mx-auto mt-8 flex max-w-md gap-2"><input class="form-input" name="q" placeholder="Cari artikel"><button class="btn-primary">Cari</button></form><a wire:navigate href="{{ route('home') }}" class="mt-8 inline-block text-indigo-700">Kembali ke beranda</a></div>
@endsection