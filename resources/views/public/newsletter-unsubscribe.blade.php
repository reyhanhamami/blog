@extends('public.layout')
@section('seo_title', 'Berhenti Berlangganan | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="mx-auto max-w-xl px-5 py-16 md:py-24">
    <p class="public-eyebrow">BESOFTON INSIGHTS</p>
    <h1 class="public-section-title mt-3">Berhenti berlangganan</h1>
    <p class="mt-4 text-stone-600">Masukkan alamat email yang digunakan untuk newsletter. Kami akan mengirim tautan konfirmasi ke alamat tersebut.</p>
    <form method="post" action="{{ route('newsletter.unsubscribe') }}" class="mt-8 space-y-4">
        @csrf
        <label for="unsubscribe-email" class="block text-sm font-semibold">Alamat email</label>
        <input id="unsubscribe-email" class="form-input" name="email" type="email" autocomplete="email" required value="{{ old('email') }}">
        @error('email')<p class="error">{{ $message }}</p>@enderror
        <button class="public-gold-button" type="submit">Berhenti berlangganan <span aria-hidden="true">→</span></button>
    </form>
</section>
@endsection
