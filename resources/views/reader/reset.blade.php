@extends('public.layout')
@section('seo_title', 'Reset password | Besofton Insights')
@section('robots', 'noindex,nofollow')
@section('content')
<div class="mx-auto max-w-md"><h1 class="text-3xl font-bold">Reset password</h1><form action="{{ route('password.update') }}" method="post" class="card mt-6 space-y-4">@csrf<input type="hidden" name="token" value="{{ $token }}"><div><label class="form-label">Email</label><input class="form-input" name="email" type="email" value="{{ $email }}" required>@error('email')<p class="error">{{ $message }}</p>@enderror</div><div><label class="form-label">Password baru</label><input class="form-input" name="password" type="password" required>@error('password')<p class="error">{{ $message }}</p>@enderror</div><div><label class="form-label">Ulangi password</label><input class="form-input" name="password_confirmation" type="password" required></div><button class="btn-primary">Simpan password</button></form></div>
@endsection