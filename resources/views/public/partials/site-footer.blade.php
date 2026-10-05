<footer class="public-site-footer">
    <section class="public-footer-newsletter" aria-labelledby="footer-newsletter-title">
        <div class="public-footer-newsletter-inner">
            <div class="public-footer-newsletter-copy">
                <p class="public-kicker">BESOFTON INSIGHTS</p>
                <h2 id="footer-newsletter-title">Jangan lewatkan<br><em>insight berikutnya.</em></h2>
                <p>Artikel, tutorial, dan materi belajar baru langsung dari Besofton.</p>
            </div>
            <div class="public-footer-newsletter-form"><livewire:newsletter-form /></div>
            <svg class="public-footer-doodle" aria-hidden="true" viewBox="0 0 120 90" fill="none"><path d="M10 71C42 71 66 35 105 15m0 0-29 1m29-1-12 27" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
    </section>
    <div class="public-footer-inner">
        <div class="public-footer-intro">
            <a wire:navigate href="{{ route('home') }}" class="public-brand public-footer-brand {{ $customLogo ? 'public-brand-custom' : '' }}" aria-label="Besofton Insights, beranda"><span class="public-brand-mark {{ $customLogo ? 'public-brand-mark-custom' : 'public-brand-mark-default' }}"><img src="{{ $logoUrl }}" alt="Besofton" loading="lazy"></span>@unless($customLogo)<span class="public-brand-text"><strong>BESOFTON</strong><small>MITRA PERTUMBUHAN DIGITAL CERDAS</small></span>@endunless</a>
            <p>Ide, panduan, dan strategi digital untuk terus tumbuh.</p>
            @if($socialLinks->isNotEmpty())
                <div class="public-footer-social" aria-label="Media sosial Besofton">
                    @foreach($socialLinks as $label => $url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }} Besofton" title="{{ $label }}"><span aria-hidden="true">{{ ['Instagram' => 'ig', 'LinkedIn' => 'in', 'YouTube' => '▶', 'GitHub' => 'GH'][$label] ?? '↗' }}</span></a>@endforeach
                </div>
            @endif
        </div>
        <nav class="public-footer-nav" aria-label="Eksplorasi"><strong>Eksplorasi</strong><a wire:navigate href="{{ route('home') }}">Insights</a><a wire:navigate href="{{ route('topics.index') }}">Topik</a><a wire:navigate href="{{ route('videos.index') }}">Video</a><a wire:navigate href="{{ route('categories.index') }}">Kategori</a></nav>
        <nav class="public-footer-nav" aria-label="Belajar"><strong>Belajar</strong><a wire:navigate href="{{ route('paths.index') }}">Jalur belajar</a><a wire:navigate href="{{ route('courses.index') }}">Kelas</a>@auth<a wire:navigate href="{{ route('reader.account') }}#learning">Lanjutkan belajar</a>@endauth</nav>
        <nav class="public-footer-nav" aria-label="Besofton"><strong>Besofton</strong>
            @foreach($footerLinks as $link)<a href="{{ $link->url }}" @if(str_starts_with($link->url, '/') && ! str_starts_with($link->url, '//')) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif>{{ $link->label }}</a>@endforeach
            @if($contactUrl && ! $footerLinks->contains('url', $contactUrl))<a href="{{ $contactUrl }}" @if(! $contactIsExternal) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif>Hubungi Kami</a>@endif
        </nav>
        <div class="public-footer-bottom"><span>© {{ date('Y') }} Besofton. Semua hak dilindungi.</span><span>Dibuat untuk berbagi insight yang berguna.</span></div>
    </div>
</footer>
