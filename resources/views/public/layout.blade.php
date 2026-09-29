@php
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

$siteName = Setting::valueFor('site_name', 'Besofton Insights');
$logoUrl = Storage::disk('public')->url('logo.png');
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
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('seo_title', $siteName.' | Artikel dan tutorial teknologi')</title>
    <meta name="description" content="@yield('seo_description', Setting::valueFor('default_description', 'Artikel dan tutorial teknologi dari Besofton Insights.'))">
    <meta name="robots" content="{{ config('app.env') === 'production' ? trim($__env->yieldContent('robots', 'index,follow')) : 'noindex,nofollow' }}">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <link rel="icon" href="{{ Setting::valueFor('favicon_url', asset('favicon.svg')) }}">
    @if(Setting::valueFor('google_verification'))<meta name="google-site-verification" content="{{ Setting::valueFor('google_verification') }}">@endif
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', $siteName)">
    <meta property="og:description" content="@yield('og_description', Setting::valueFor('default_description', 'Artikel dan tutorial teknologi dari Besofton Insights.'))">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    @if(trim($__env->yieldContent('og_image')) !== '')<meta property="og:image" content="@yield('og_image')"><meta name="twitter:card" content="summary_large_image">@endif
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/public.css', 'resources/js/public.js'])
    @endif
    @php $siteSchema = ['@context' => 'https://schema.org', '@graph' => [['@type' => 'Organization', 'name' => $siteName, 'url' => route('home')], ['@type' => 'WebSite', 'name' => $siteName, 'url' => route('home')]]]; @endphp
    <script type="application/ld+json">{!! json_encode($siteSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @livewireStyles
    @stack('head')
</head>
<body class="public-body">
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
