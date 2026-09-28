<footer class="public-site-footer">
    <div class="public-footer-inner">
        <div class="public-footer-intro"><a wire:navigate href="{{ route('home') }}" class="public-brand public-footer-brand" aria-label="Besofton Insights, beranda"><span class="public-brand-mark"><img src="{{ $logoUrl }}" alt="" width="100" height="100" loading="lazy"></span><span class="public-brand-text"><strong>BESOFTON</strong><small>MITRA PERTUMBUHAN DIGITAL CERDAS</small></span></a><p>Ide, panduan, dan strategi digital untuk terus tumbuh.</p></div>
        <nav class="public-footer-nav" aria-label="Navigasi footer"><strong>Jelajahi</strong>@foreach($footerLinks as $link)<a href="{{ $link->url }}" @if(str_starts_with($link->url, '/')) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif>{{ $link->label }}</a>@endforeach @unless($footerLinks->contains('url', '/'))<a wire:navigate href="{{ route('home') }}">Insights</a>@endunless</nav>
        @if($contactUrl || $socialLinks->isNotEmpty())<div class="public-footer-connect"><strong>Terhubung</strong>@if($contactUrl)<a href="{{ $contactUrl }}" @if(! $contactIsExternal) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif>Hubungi Kami ↗</a>@endif @foreach($socialLinks as $label => $url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>@endforeach</div>@endif
        <div class="public-footer-bottom"><span>© {{ date('Y') }} Besofton. Semua hak dilindungi.</span><span>Besofton Insights</span></div>
    </div>
</footer>
