<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ \App\Support\Branding::faviconUrl() }}"><title>@yield('title', 'CMS') · Besofton Insights CMS</title>
    @vite(['resources/css/admin.css','resources/js/admin.js'])
    @livewireStyles
</head>
<body data-cms="1" class="admin-app bg-slate-50 text-slate-900">
@php
    $sidebarGroups = collect(config('cms_navigation'))->map(fn ($items) => collect($items)->filter(fn ($item) => auth()->user()->canAny($item['permissions']))->values())->filter(fn ($items) => $items->isNotEmpty());
@endphp
<div id="nav-progress" class="fixed left-0 top-0 z-50 hidden h-1 w-full animate-pulse bg-indigo-600"></div>
<div class="admin-shell min-h-screen lg:flex" x-data="{ sidebarOpen: false, profileOpen: false }" @keydown.escape.window="sidebarOpen = false; profileOpen = false">
    <aside class="w-full shrink-0 border-b border-slate-800 bg-slate-950 text-white lg:min-h-screen lg:w-64 lg:border-b-0">
        <div class="flex items-center justify-between p-5 lg:block">
            <div><a href="{{ route('admin.dashboard') }}" wire:navigate class="text-xl font-bold tracking-tight">Besofton <span class="text-indigo-300">Insights</span></a><p class="mt-1 text-xs text-slate-400">Content Management System</p></div>
            <button type="button" class="rounded-lg border border-slate-600 px-3 py-2 text-xs lg:hidden" @click="sidebarOpen = ! sidebarOpen" :aria-expanded="sidebarOpen.toString()" aria-controls="cms-sidebar-nav">Menu</button>
        </div>
        <nav id="cms-sidebar-nav" class="max-h-[calc(100vh-7rem)] overflow-y-auto px-3 pb-6 lg:block" :class="sidebarOpen ? 'block' : 'hidden lg:block'" aria-label="Navigasi CMS">
            @foreach($sidebarGroups as $group => $items)
                <div class="mb-5">
                    <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[.18em] text-slate-500">{{ $group }}</p>
                    @foreach($items as $item)
                        @php $active = request()->routeIs(...$item['active']); @endphp
                        <a wire:navigate href="{{ route($item['route']) }}" @click="sidebarOpen = false" class="cms-nav flex items-center gap-3 {{ $active ? 'cms-active' : '' }}" @if($active) aria-current="page" @endif>
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $active ? 'bg-indigo-300' : 'bg-slate-600' }}" aria-hidden="true"></span>{{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>
    <div class="admin-main min-w-0 flex-1">
        <header class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-3">
            <div class="text-sm text-slate-500">Besofton Insights CMS</div>
            <div class="relative" @click.outside="profileOpen = false">
                <button type="button" class="flex items-center gap-3 rounded-lg px-2 py-1 text-left hover:bg-slate-100" @click="profileOpen = ! profileOpen" :aria-expanded="profileOpen.toString()" aria-controls="cms-user-menu">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-800">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="hidden text-sm sm:block"><strong class="block font-semibold">{{ auth()->user()->name }}</strong><small class="text-slate-500">{{ auth()->user()->roleRecord?->display_name ?? ucfirst(auth()->user()->role) }}</small></span>
                    <span aria-hidden="true">⌄</span>
                </button>
                <div id="cms-user-menu" x-show="profileOpen" x-cloak class="absolute right-0 z-40 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                    <p class="border-b border-slate-100 px-3 py-2 text-xs text-slate-500">{{ auth()->user()->email }}</p>
                    <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100">Lihat Blog ↗</a>
                    <a href="{{ route('reader.account') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-100">Profil</a>
                    <form action="{{ route('admin.logout') }}" method="post">@csrf<button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-100">Keluar</button></form>
                </div>
            </div>
        </header>
        <main class="mx-auto max-w-7xl p-5 lg:p-8" wire:transition.navigate>
            @if(session('success'))<div data-toast data-toast-icon="success" hidden>{{ session('success') }}</div>@endif
            @if(session('error'))<div data-toast data-toast-icon="error" hidden>{{ session('error') }}</div>@endif
            @yield('content')
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
