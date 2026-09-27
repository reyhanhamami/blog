<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="{{ asset('favicon.svg') }}"><title>@yield('title', 'CMS') · Besofton Insights CMS</title>
@if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) @vite(['resources/css/app.css','resources/js/app.js']) @endif
@livewireStyles
</head>
<body data-cms="1" class="bg-slate-50 text-slate-900">
<div id="nav-progress" class="fixed left-0 top-0 z-50 hidden h-1 w-full animate-pulse bg-indigo-600"></div>
<div class="min-h-screen lg:flex">
<aside class="w-full shrink-0 border-b border-slate-200 bg-slate-950 p-5 text-white lg:min-h-screen lg:w-64 lg:border-b-0">
<a href="{{ route('admin.dashboard') }}" wire:navigate class="text-xl font-bold tracking-tight">Besofton <span class="text-indigo-300">Insights</span></a>
<p class="mb-8 mt-1 text-xs text-slate-400">Content Management System</p>
<nav class="grid grid-cols-2 gap-1 text-sm lg:block">
<a wire:navigate.hover href="{{ route('admin.dashboard') }}" class="cms-nav {{ request()->routeIs('admin.dashboard') ? 'cms-active' : '' }}">Dashboard</a>
<a wire:navigate.hover href="{{ route('admin.posts.index') }}" class="cms-nav {{ request()->routeIs('admin.posts.*') ? 'cms-active' : '' }}">Artikel</a>
@can('manage-content')
@foreach(['categories' => 'Kategori', 'tags' => 'Tag', 'topics' => 'Topik', 'authors' => 'Penulis', 'videos' => 'Video', 'quizzes' => 'Kuis', 'learning-paths' => 'Jalur Belajar', 'courses' => 'Kelas'] as $module => $label)
<a wire:navigate.hover href="{{ route('admin.'.$module.'.index') }}" class="cms-nav {{ request()->routeIs('admin.'.$module.'.*') ? 'cms-active' : '' }}">{{ $label }}</a>
@endforeach
<a wire:navigate.hover href="{{ route('admin.pages.index') }}" class="cms-nav {{ request()->routeIs('admin.pages.*') ? 'cms-active' : '' }}">Halaman</a>
<a wire:navigate.hover href="{{ route('admin.questions.index') }}" class="cms-nav {{ request()->routeIs('admin.questions.*') ? 'cms-active' : '' }}">Tanya jawab</a>
<a wire:navigate.hover href="{{ route('admin.media.index') }}" class="cms-nav {{ request()->routeIs('admin.media.*') ? 'cms-active' : '' }}">Media</a>
<a wire:navigate.hover href="{{ route('admin.editorial-calendar') }}" class="cms-nav {{ request()->routeIs('admin.editorial-calendar') ? 'cms-active' : '' }}">Kalender editorial</a>
<a wire:navigate.hover href="{{ route('admin.analytics') }}" class="cms-nav {{ request()->routeIs('admin.analytics') ? 'cms-active' : '' }}">Analitik</a>
@endcan
@can('manage-system')
<a wire:navigate.hover href="{{ route('admin.menus.index') }}" class="cms-nav {{ request()->routeIs('admin.menus.*') ? 'cms-active' : '' }}">Menu</a>
<a wire:navigate.hover href="{{ route('admin.redirects.index') }}" class="cms-nav {{ request()->routeIs('admin.redirects.*') ? 'cms-active' : '' }}">Redirect</a>
<a wire:navigate.hover href="{{ route('admin.users.index') }}" class="cms-nav {{ request()->routeIs('admin.users.*') ? 'cms-active' : '' }}">Pengguna</a>
<a wire:navigate.hover href="{{ route('admin.settings.edit') }}" class="cms-nav {{ request()->routeIs('admin.settings.*') ? 'cms-active' : '' }}">Pengaturan</a>
@endcan
</nav>
</aside>
<div class="min-w-0 flex-1">
<header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4">
<div class="text-sm text-slate-500">{{ auth()->user()->name }} · {{ auth()->user()->role }}</div>
<div class="flex items-center gap-4 text-sm"><a wire:navigate href="{{ route('home') }}" class="text-indigo-700 hover:underline">Lihat blog ↗</a><form action="{{ route('admin.logout') }}" method="post">@csrf<button class="text-slate-600 hover:text-red-600">Keluar</button></form></div>
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