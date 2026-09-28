@extends('public.layout')
@section('seo_title', 'Halaman tidak ditemukan | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="public-error-page"><p class="public-error-code">404</p><p class="public-kicker">HALAMAN TIDAK DITEMUKAN</p><h1>Sepertinya Anda tersesat.</h1><p>Cari topik lain atau kembali ke beranda untuk menemukan ide baru.</p><form action="{{ route('search') }}" method="get" class="public-search-fields" role="search"><label class="sr-only" for="error-search">Cari artikel</label><input id="error-search" class="public-input" name="q" type="search" placeholder="Cari artikel atau topik"><button class="public-button is-gold">Cari →</button></form><a wire:navigate href="{{ route('home') }}" class="public-outline-button">Kembali ke beranda</a></div>
@endsection
