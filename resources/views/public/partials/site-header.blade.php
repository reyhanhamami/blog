<header class="public-site-header" x-data="{ open: false }" @keydown.escape.window="open = false" x-effect="document.body.style.overflow = open ? 'hidden' : ''">
    <div class="public-header-inner">
        <a wire:navigate href="{{ route('home') }}" class="public-brand{{ $customLogo ? ' public-brand-custom' : '' }}" aria-label="Besofton Insights, beranda">
            <span class="public-brand-mark {{ $customLogo ? 'public-brand-mark-custom' : 'public-brand-mark-default' }}"><img src="{{ $logoUrl }}" alt="Besofton"></span>
            @unless($customLogo)<span class="public-brand-text"><strong>BESOFTON</strong><small>MITRA PERTUMBUHAN DIGITAL CERDAS</small></span>@endunless
        </a>
        <nav class="public-desktop-nav" aria-label="Navigasi utama">
            @foreach($headerLinks as $link)
                @php $isInternal = str_starts_with($link->url, '/') && ! str_starts_with($link->url, '//'); $active = \App\Support\PublicNavigation::isActive($link->url); @endphp
                <a href="{{ $link->url }}" @if($isInternal) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif class="{{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>{{ $link->label }}</a>
            @endforeach
        </nav>
        <div class="public-header-actions">
            <a wire:navigate href="{{ route('search') }}" class="public-search-link {{ request()->routeIs('search') ? 'is-active' : '' }}" aria-label="Cari artikel dan topik" @if(request()->routeIs('search')) aria-current="page" @endif>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg><span>Cari</span>
            </a>
            @auth
                @php
                    $headerUser = auth()->user();
                    $headerPhoto = $headerUser->profile_photo_path;
                    $hasHeaderPhoto = $headerPhoto && (
                        \Illuminate\Support\Str::startsWith($headerPhoto, ['https://', 'http://'])
                        || (\Illuminate\Support\Str::startsWith($headerPhoto, ['images/', 'storage/', '/images/', '/storage/']) && is_file(public_path(ltrim($headerPhoto, '/'))))
                        || \Illuminate\Support\Facades\Storage::disk('public')->exists($headerPhoto)
                    );
                @endphp
                <div class="public-user-menu" x-data="{ expanded: false }" @click.outside="expanded = false" @keydown.escape.window="expanded = false">
                    <button type="button" class="public-account-link" @click="expanded = !expanded" :aria-expanded="expanded.toString()" aria-controls="public-account-menu">
                        <span class="public-account-avatar" aria-hidden="true">@if($hasHeaderPhoto)<img src="{{ $headerUser->profile_photo_url }}" alt="" width="26" height="26">@else{{ mb_strtoupper(mb_substr($headerUser->display_name, 0, 1)) }}@endif</span>
                        <span class="public-account-name">{{ \Illuminate\Support\Str::limit($headerUser->display_name, 18) }}</span><span class="public-account-caret" aria-hidden="true">&#8964;</span>
                    </button>
                    <div id="public-account-menu" class="public-account-dropdown" x-show="expanded" x-cloak>
                        <a wire:navigate href="{{ route('reader.account') }}">Akun</a>
                        <a wire:navigate href="{{ route('reader.account') }}#bookmarks">Bookmark</a>
                        <a wire:navigate href="{{ route('reader.account') }}#learning">Lanjutkan Belajar</a>
                        @can('cms.access')<a href="{{ route('admin.dashboard') }}">Masuk ke CMS</a>@endcan
                        <form method="post" action="{{ route('reader.logout') }}">@csrf<button type="submit">Keluar</button></form>
                    </div>
                </div>
            @else
                <a wire:navigate href="{{ route('login') }}" class="public-account-link public-login-link">Masuk <span aria-hidden="true">&rarr;</span></a>
            @endauth
        </div>
        <button type="button" class="public-menu-toggle" @click="open = !open" :aria-expanded="open.toString()" aria-controls="public-mobile-menu" :aria-label="open ? 'Tutup navigasi' : 'Buka navigasi'"><span></span><span></span><span></span></button>
    </div>
    <nav id="public-mobile-menu" class="public-mobile-menu" x-show="open" x-cloak aria-label="Navigasi seluler">
        @foreach($headerLinks as $link)
            @php $isInternal = str_starts_with($link->url, '/') && ! str_starts_with($link->url, '//'); $active = \App\Support\PublicNavigation::isActive($link->url); @endphp
            <a href="{{ $link->url }}" @if($isInternal) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif @click="open = false" class="{{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>{{ $link->label }}</a>
        @endforeach
        <div class="public-mobile-divider" aria-hidden="true"></div>
        <a wire:navigate href="{{ route('search') }}" @click="open = false">Cari artikel</a>
        @auth
            <a wire:navigate href="{{ route('reader.account') }}" @click="open = false">Akun</a>
            <a wire:navigate href="{{ route('reader.account') }}#bookmarks" @click="open = false">Bookmark</a>
            <a wire:navigate href="{{ route('reader.account') }}#learning" @click="open = false">Lanjutkan Belajar</a>
            @can('cms.access')<a href="{{ route('admin.dashboard') }}" @click="open = false">Masuk ke CMS</a>@endcan
            <form method="post" action="{{ route('reader.logout') }}">@csrf<button type="submit">Keluar</button></form>
        @else
            <a wire:navigate href="{{ route('login') }}" @click="open = false">Masuk</a>
        @endauth
    </nav>
</header>
