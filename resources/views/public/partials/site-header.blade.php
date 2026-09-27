<header class="public-site-header" x-data="{ open: false }" @keydown.escape.window="open = false" x-effect="document.body.style.overflow = open ? 'hidden' : ''">
    <div class="public-header-inner">
        <a wire:navigate href="{{ route('home') }}" class="public-brand" aria-label="Besofton Insights — Beranda">
            <span class="public-brand-mark"><img src="{{ $logoUrl }}" alt="" width="100" height="100"></span>
            <span class="public-brand-text"><strong>BESOFTON</strong><small>MITRA PERTUMBUHAN DIGITAL CERDAS</small></span>
        </a>
        <nav class="public-desktop-nav" aria-label="Navigasi utama">
            @foreach($headerLinks as $link)
                @php $isInsights = $link->url === '/'; $isInternal = str_starts_with($link->url, '/'); @endphp
                <a href="{{ $link->url }}" @if($isInternal) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif class="{{ $isInsights ? 'is-active' : '' }}" @if($isInsights) aria-current="page" @endif>{{ $isInsights ? 'Insights' : $link->label }}</a>
            @endforeach
            @unless($headerLinks->contains('url', '/'))<a wire:navigate href="{{ route('home') }}" class="is-active" aria-current="page">Insights</a>@endunless
        </nav>
        <div class="public-header-actions">
            <div class="public-language" aria-label="Bahasa situs"><span class="is-active" lang="id">ID</span><span aria-disabled="true" title="Bahasa Inggris belum tersedia">EN</span></div>
            @if($contactUrl)
                <a href="{{ $contactUrl }}" @if(! $contactIsExternal) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif class="public-contact-button">Hubungi Kami <span aria-hidden="true">↗</span></a>
            @endif
            @auth<a wire:navigate href="{{ route('reader.account') }}" class="public-account-link">Akun</a>
            @else<a wire:navigate href="{{ route('login') }}" class="public-account-link">Masuk</a>@endauth
        </div>
        <button type="button" class="public-menu-toggle" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="public-mobile-menu" aria-label="Buka navigasi">
            <span></span><span></span><span></span>
        </button>
    </div>
    <nav id="public-mobile-menu" class="public-mobile-menu" x-show="open" x-cloak aria-label="Navigasi seluler">
        @foreach($headerLinks as $link)
            @php $isInternal = str_starts_with($link->url, '/'); @endphp
            <a href="{{ $link->url }}" @if($isInternal) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif @click="open = false">{{ $link->url === '/' ? 'Insights' : $link->label }}</a>
        @endforeach
        @unless($headerLinks->contains('url', '/'))<a wire:navigate href="{{ route('home') }}" @click="open = false">Insights</a>@endunless
        @if($contactUrl)<a href="{{ $contactUrl }}" @if(! $contactIsExternal) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif @click="open = false">Hubungi Kami ↗</a>@endif
        @auth<a wire:navigate href="{{ route('reader.account') }}" @click="open = false">Akun</a>
        @else<a wire:navigate href="{{ route('login') }}" @click="open = false">Masuk</a>@endauth
    </nav>
</header>
