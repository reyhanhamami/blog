@extends('public.layout')
@section('seo_title', $page->title.' | Besofton Insights')
@section('seo_description', \Illuminate\Support\Str::limit(strip_tags($page->content), 155))
@section('content')
<div class="public-shell">
    <nav aria-label="Breadcrumb" class="public-breadcrumb"><a wire:navigate href="{{ route('home') }}">Beranda</a><span>/</span><span aria-current="page">{{ $page->title }}</span></nav>
    <header class="public-page-hero"><p class="public-kicker">BESOFTON INSIGHTS</p><h1>{{ $page->title }}</h1></header>
    <article class="public-narrow article-content prose-besofton">{!! $page->content !!}
    @if($page->slug === 'privacy-policy')
    <section><h2>Analitik pengunjung first-party</h2><p>Besofton Insights menggunakan cookie anonim <code>besofton_visitor</code> dengan masa berlaku 90 hari untuk memperkirakan browser berbeda dan memahami halaman yang dibaca. Cookie ini terpisah dari akun/login dan tidak dapat dibaca JavaScript (HttpOnly). Penghitungan dilakukan setelah halaman tampil, termasuk navigasi ant halaman.</p><p>Kami menyimpan jumlah tayangan harian serta hash pengenal anonim untuk deduplikasi. IP mentah, isi cookie autentikasi, user-agent, lokasi, dan fingerprint perangkat tidak disimpan dalam database analytics. Tidak ada layanan tracking pihak ketiga. Pengunjung unik merupakan estimasi browser, bukan identitas orang.</p><p>Detail keanggotaan pengunjung dihapus melalui retensi 90 hari, bukti deduplikasi request sekitar 24 jam, dan agregat harian disimpan hingga tiga tahun. Counter total artikel tetap disimpan bersama artikel. Statistik hanya dapat diakses Superadmin dan digunakan untuk evaluasi konten atau laporan kerja sama; tayangan artikel tidak dianggap sebagai impression iklan.</p><p>Permintaan dengan sinyal Do Not Track (DNT) atau Global Privacy Control (GPC) tidak dilacak. Memblokir cookie/JavaScript dapat memengaruhi kelengkapan penghitungan. Cookie analitik tidak digunakan untuk mengenali akun Anda.</p></section>
    @endif
    </article>
</div>
@endsection
