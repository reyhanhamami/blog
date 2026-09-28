@extends(request()->is('admin*') && auth()->check() ? 'admin.layout' : 'public.layout')
@section('title', 'Akses ditolak')
@section('seo_title', 'Akses tidak tersedia | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
@if(request()->is('admin*') && auth()->check())
<div class="rounded-xl border border-slate-200 bg-white p-8"><h1 class="text-2xl font-bold">Akses ditolak</h1><p class="mt-3 text-slate-600">Akun Anda tidak memiliki izin untuk membuka halaman ini.</p><a wire:navigate href="{{ route('admin.dashboard') }}" class="mt-5 inline-block text-indigo-700 underline">Kembali ke CMS</a></div>
@else
<div class="public-error-page"><p class="public-error-code">403</p><p class="public-kicker">AKSES TERBATAS</p><h1>Halaman ini belum bisa Anda buka.</h1><p>Masuk dengan akun yang memiliki akses, atau kembali menjelajahi Insights.</p><div class="public-form-actions"><a wire:navigate href="{{ route('login') }}" class="public-button is-gold">Masuk</a><a wire:navigate href="{{ route('home') }}" class="public-outline-button">Ke beranda</a></div></div>
@endif
@endsection
