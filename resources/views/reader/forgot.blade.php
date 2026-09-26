@extends('public.layout')
@section('seo_title', 'Lupa password | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="mx-auto max-w-md"><h1 class="text-3xl font-bold">Lupa password</h1><p class="mt-2 text-slate-600">Masukkan email akun untuk menerima tautan reset.</p><form action="{{ route('password.email') }}" method="post" class="card mt-6 space-y-4">@csrf<div><label class="form-label">Email</label><input class="form-input" type="email" name="email" required>@error('email')<p class="error">{{ $message }}</p>@enderror</div><button class="btn-primary">Kirim tautan</button></form></div>
@endsection