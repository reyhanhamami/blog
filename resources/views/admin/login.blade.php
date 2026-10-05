<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="{{ \App\Support\Branding::faviconUrl() }}"><title>Masuk · Besofton Insights CMS</title>@vite(['resources/css/admin.css','resources/js/admin.js'])</head>
<body class="flex min-h-screen items-center justify-center bg-slate-950 p-5 text-slate-900">
<div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl"><a href="{{ route('home') }}" class="text-xl font-bold text-indigo-700">Besofton Insights</a><h1 class="mt-8 text-2xl font-bold">Masuk ke CMS</h1><p class="mt-2 text-sm text-slate-500">Kelola konten dan pengetahuan Besofton.</p>
<form method="post" action="{{ route('admin.login.store') }}" class="mt-7 space-y-5">@csrf
<div><label for="email" class="form-label">Email</label><input id="email" name="email" type="email" required autocomplete="username" value="{{ old('email') }}" class="form-input">@error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
<div><label for="password" class="form-label">Password</label><input id="password" name="password" type="password" required autocomplete="current-password" class="form-input">@error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
<button type="submit" class="btn-primary w-full">Masuk</button>
</form></div></body></html>
