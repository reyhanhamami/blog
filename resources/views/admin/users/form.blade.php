@extends('admin.layout')
@section('title', $user->exists ? 'Edit User' : 'Tambah User')
@section('content')
<div class="mb-6"><a wire:navigate href="{{ route('admin.users.index') }}" class="text-sm font-medium text-indigo-700">← Users & Access</a><h1 class="mt-4 text-3xl font-bold">{{ $user->exists ? 'Edit User' : 'Tambah User' }}</h1><p class="mt-2 text-sm text-slate-500">Atur identitas dan role utama pengguna.</p></div>
<form action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" method="post" class="card max-w-2xl space-y-5">
    @csrf @if($user->exists) @method('PATCH') @endif
    <div><label class="form-label" for="user-name">Nama *</label><input id="user-name" class="form-input" name="name" required value="{{ old('name', $user->name) }}">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
    <div><label class="form-label" for="user-email">Email *</label><input id="user-email" class="form-input" type="email" name="email" required value="{{ old('email', $user->email) }}">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
    <div><label class="form-label" for="user-role">Role *</label><select id="user-role" name="role" class="form-input" data-search-select required><option value="">Pilih role</option>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(old('role', $user->role) === $role->name)>{{ $role->display_name }}</option>@endforeach</select><p class="mt-1 text-xs text-slate-500">Hak akses mengikuti permissions role terpilih.</p>@error('role')<p class="error">{{ $message }}</p>@enderror</div>
    <div><label class="form-label" for="user-password">Password {{ $user->exists ? '(opsional)' : '*' }}</label><input id="user-password" class="form-input" type="password" name="password" @if(! $user->exists) required @endif autocomplete="new-password" minlength="8">@if($user->exists)<p class="mt-1 text-xs text-slate-500">Kosongkan jika tidak ingin mengganti password.</p>@endif @error('password')<p class="error">{{ $message }}</p>@enderror</div>
    <div class="flex gap-3"><button type="submit" class="btn-primary">Simpan User</button><a wire:navigate href="{{ route('admin.users.index') }}" class="btn-secondary">Batal</a></div>
</form>
@endsection
