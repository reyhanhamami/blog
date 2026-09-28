@extends('public.layout')
@section('seo_title', 'Lupa password | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="public-auth"><div class="public-auth-intro"><p class="public-kicker">AKUN PEMBACA</p><h1>Atur ulang akses Anda.</h1><p>Kami akan mengirim tautan pemulihan ke alamat email akun Anda.</p></div><div class="public-panel public-auth-panel"><h2>Lupa password</h2><form action="{{ route('password.email') }}" method="post" class="public-form-stack">@csrf<div class="public-field"><label for="forgot-email">Email</label><input id="forgot-email" class="form-input" type="email" name="email" autocomplete="email" required>@error('email')<p class="error">{{ $message }}</p>@enderror</div><button class="public-button is-gold" type="submit">Kirim tautan →</button></form><div class="public-inline-links"><a wire:navigate href="{{ route('login') }}">Kembali ke masuk</a></div></div></div>
@endsection
