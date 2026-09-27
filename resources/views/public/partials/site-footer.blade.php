<footer class="public-site-footer">
    <div class="public-footer-inner">
        <a wire:navigate href="{{ route('home') }}" class="public-brand public-footer-brand" aria-label="Besofton Insights — Beranda">
            <span class="public-brand-mark"><img src="{{ $logoUrl }}" alt="" width="100" height="100" loading="lazy"></span>
            <span class="public-brand-text"><strong>BESOFTON</strong><small>MITRA PERTUMBUHAN DIGITAL CERDAS</small></span>
        </a>
        <nav class="public-footer-nav" aria-label="Navigasi footer">
            @foreach($footerLinks as $link)
                <a href="{{ $link->url }}" @if(str_starts_with($link->url, '/')) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif>{{ $link->label }}</a>
            @endforeach
            <a wire:navigate href="{{ route('home') }}" class="is-active">Insights</a>
        </nav>
        <div class="public-footer-end">
            @if($socialLinks->isNotEmpty())
            <nav class="public-social" aria-label="Media sosial">
                @foreach($socialLinks as $label => $url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}">{{ $label }}</a>@endforeach
            </nav>
            @endif
            <small>© {{ date('Y') }} Besofton. All rights reserved.</small>
        </div>
    </div>
</footer>
