@extends('public.layout')
@section('seo_title', 'Terjadi kendala | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="public-error-page"><p class="public-error-code">500</p><p class="public-kicker">ADA KENDALA</p><h1>Kami sedang memperbaikinya.</h1><p>Silakan coba lagi beberapa saat, atau kembali ke beranda.</p><a wire:navigate href="{{ route('home') }}" class="public-button is-gold">Ke beranda →</a></div>
@endsection
