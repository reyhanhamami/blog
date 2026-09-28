@extends('public.layout')
@section('seo_title', 'Berhenti Berlangganan | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="public-narrow"><nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">Newsletter</span></nav><header class="public-page-hero"><p class="public-kicker">BESOFTON INSIGHTS</p><h1>Berhenti berlangganan</h1><p>Masukkan alamat email newsletter Anda. Kami akan mengirim tautan konfirmasi.</p></header><form method="post" action="{{ route('newsletter.unsubscribe') }}" class="public-panel public-form-stack public-section">@csrf<div class="public-field"><label for="unsubscribe-email">Alamat email</label><input id="unsubscribe-email" class="form-input" name="email" type="email" autocomplete="email" required value="{{ old('email') }}">@error('email')<p class="error">{{ $message }}</p>@enderror</div><button class="public-button is-gold" type="submit">Berhenti berlangganan →</button></form></div>
@endsection
