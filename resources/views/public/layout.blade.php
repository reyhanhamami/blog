@php
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;

$siteName = Setting::valueFor('site_name', 'Besofton Insights');
$publicTheme = \App\Support\PublicTheme::resolve();
$logoUrl = \App\Support\Branding::logoUrl();
$customLogo = (bool) (\App\Support\Branding::uploadedPath('logo_path') || Setting::valueFor('logo_url'));
$headerLinks = Menu::links('header')->filter(fn ($link) => $link->parent_id === null && $link->url !== '/cari')->values();
if ($headerLinks->isEmpty()) {
    $headerLinks = \App\Support\PublicNavigation::fallback();
}
$footerLinks = Menu::links('footer');
$contactUrl = Setting::valueFor('contact_url');
if (! $contactUrl && Page::where('slug', 'contact')->where('is_published', true)->exists()) {
    $contactUrl = route('page.contact');
}
$contactIsExternal = $contactUrl && preg_match('~^https?://~i', $contactUrl);
if ($contactUrl && ! $contactIsExternal && ! str_starts_with($contactUrl, '/')) {
    $contactUrl = '';
}
$socialLinks = collect([
    'Instagram' => Setting::valueFor('instagram_url'),
    'LinkedIn' => Setting::valueFor('linkedin_url'),
    'YouTube' => Setting::valueFor('youtube_url'),
    'GitHub' => Setting::valueFor('github_url'),
])->filter(fn ($url) => (bool) preg_match('~^https?://~i', $url));
$resolvedTitle = trim($__env->yieldContent('seo_title', $siteName.' | Artikel dan tutorial teknologi'));
$resolvedDescription = trim($__env->yieldContent('seo_description', Setting::valueFor('default_description', 'Artikel dan tutorial teknologi dari Besofton Insights.')));
$canonicalUrl = \App\Support\PublicSeo::canonical($__env->yieldContent('canonical', url()->current()));
$socialTitle = trim($__env->yieldContent('og_title')) ?: $resolvedTitle;
$socialDescription = trim($__env->yieldContent('og_description')) ?: $resolvedDescription;
$socialImage = \App\Support\PublicSeo::image(trim($__env->yieldContent('og_image')) ?: Setting::valueFor('default_og_image'));
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="{{ $publicTheme['header-bg'] }}">
    <title>{{ $resolvedTitle }}</title>
    <meta name="description" content="{{ $resolvedDescription }}">
    <meta name="robots" content="{{ config('app.env') === 'production' ? trim($__env->yieldContent('robots', 'index,follow')) : 'noindex,nofollow' }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" type="application/rss+xml" title="{{ $siteName }}" href="{{ route('feed') }}">
    <link rel="icon" href="{{ \App\Support\Branding::faviconUrl() }}">
    @if(Setting::valueFor('google_verification'))<meta name="google-site-verification" content="{{ Setting::valueFor('google_verification') }}">@endif
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $socialTitle }}">
    <meta property="og:description" content="{{ $socialDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta name="twitter:card" content="{{ $socialImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $socialTitle }}">
    <meta name="twitter:description" content="{{ $socialDescription }}">
    @if($socialImage)<meta property="og:image" content="{{ $socialImage }}"><meta name="twitter:image" content="{{ $socialImage }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap">
    @vite(['resources/css/public.css', 'resources/js/public.js'])
    @php $siteSchema = ['@context' => 'https://schema.org', '@graph' => [['@type' => 'Organization', 'name' => $siteName, 'url' => route('home')], ['@type' => 'WebSite', 'name' => $siteName, 'url' => route('home')]]]; @endphp
    <script type="application/ld+json">{!! json_encode($siteSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @livewireStyles
    @stack('head')
</head>
<body class="public-body public-app" style="{{ \App\Support\PublicTheme::style($publicTheme) }}">
    <div id="nav-progress" hidden aria-hidden="true"></div>
    @include('public.partials.site-header')
    <main class="public-main @yield('main_class')" wire:transition.navigate>
        @if(session('success'))<div data-toast hidden>{{ session('success') }}</div>@endif
        @yield('content')
    </main>
    @include('public.partials.site-footer')
    @livewireScripts
</body>
</html>
