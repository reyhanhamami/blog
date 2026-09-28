@extends('public.layout')
@section('seo_title', 'Akun pembaca | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="public-shell">
    <header class="public-page-hero public-account-hero"><div><p class="public-kicker">RUANG PEMBACA</p><h1>Halo, {{ auth()->user()->name }}</h1><p>Lanjutkan membaca dan belajar sesuai ritme Anda.</p></div><form action="{{ route('reader.logout') }}" method="post">@csrf<button class="public-outline-button">Keluar</button></form></header>
    <div class="public-account-grid public-section">
        <section id="bookmarks" class="public-panel"><p class="public-kicker">SIMPANAN</p><h2>Artikel tersimpan</h2>@forelse($bookmarks as $post)<a wire:navigate href="{{ route('post.show', $post->slug) }}" class="public-account-item">{{ $post->title }} <span aria-hidden="true">→</span></a>@empty<div class="public-empty"><p>Belum ada bookmark.</p><a wire:navigate href="{{ route('home') }}" class="public-outline-button">Jelajahi artikel</a></div>@endforelse</section>
        <section class="public-panel"><p class="public-kicker">RIWAYAT</p><h2>Baru dibaca</h2>@forelse($history as $post)<a wire:navigate href="{{ route('post.show', $post->slug) }}" class="public-account-item">{{ $post->title }} <span aria-hidden="true">→</span></a>@empty<div class="public-empty"><p>Belum ada riwayat bacaan.</p></div>@endforelse</section>
        <section id="learning" class="public-panel"><p class="public-kicker">BELAJAR</p><h2>Pembelajaran</h2><p>{{ $completed }} pelajaran selesai</p>@if($continueLearning)<div class="public-continue"><h3>Lanjutkan Belajar</h3><strong>{{ $continueLearning['course']->title }}</strong><p>{{ $continueLearning['lesson']->title }} · {{ $continueLearning['completed'] }} / {{ $continueLearning['total'] }} selesai</p><a wire:navigate href="{{ route('course.lesson', [$continueLearning['course'], $continueLearning['lesson']]) }}" class="public-button is-gold">Lanjutkan →</a></div>@elseif($completed > 0 && $hasCourseActivity)<p class="public-continue">Course selesai</p>@else<div class="public-empty"><p>Belum ada kelas aktif.</p><a wire:navigate href="{{ route('home') }}" class="public-outline-button">Jelajahi Insights</a></div>@endif</section>
        <section class="public-panel"><p class="public-kicker">HASIL</p><h2>Hasil kuis</h2>@forelse($attempts as $attempt)<p class="public-account-item">{{ $attempt->title }} <strong>{{ $attempt->score }}%</strong></p>@empty<div class="public-empty"><p>Belum ada percobaan kuis.</p></div>@endforelse</section>
    </div>
</div>
@endsection
