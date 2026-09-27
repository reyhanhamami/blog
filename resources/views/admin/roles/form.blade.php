@extends('admin.layout')
@section('title', $role->exists ? 'Edit Role' : 'Tambah Role')
@section('content')
@php $allNames = collect($groups)->flatMap(fn ($items) => array_keys($items))->values()->all(); @endphp
<div class="mb-6"><a wire:navigate href="{{ route('admin.users.index', ['tab' => 'roles']) }}" class="text-sm font-medium text-indigo-700">← Roles & Permissions</a><h1 class="mt-4 text-3xl font-bold">{{ $role->exists ? 'Kelola '.$role->display_name : 'Tambah Role' }}</h1><p class="mt-2 text-sm text-slate-500">Pilih kemampuan yang sesuai dengan tanggung jawab role ini. Perubahan disimpan sekaligus.</p></div>
<form action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" method="post" data-permission-form x-data="permissionMatrix(@js(old('permissions', $selected)), @js($allNames))" @submit="$el.dataset.saving = '1'; $el.dataset.dirty = '0'" class="space-y-6">
    @csrf @if($role->exists) @method('PATCH') @endif
    <section class="card grid gap-4 md:grid-cols-2">
        @if(! $role->exists)<div><label class="form-label" for="role-name">Kode role *</label><input id="role-name" class="form-input" name="name" value="{{ old('name') }}" maxlength="20" pattern="[A-Za-z0-9_-]+" required placeholder="seo_specialist"><p class="mt-1 text-xs text-slate-500">Kode unik, maksimal 20 karakter. Tidak dapat diubah setelah role dibuat.</p>@error('name')<p class="error">{{ $message }}</p>@enderror</div>
        @else<div><span class="form-label">Kode role</span><p class="rounded-lg bg-slate-50 p-3 text-sm">{{ $role->name }} @if($role->is_system)<span class="ml-2 rounded-full bg-indigo-100 px-2 py-1 text-xs text-indigo-700">System Role</span>@endif</p></div>@endif
        <div><label class="form-label" for="role-display">Nama tampilan *</label><input id="role-display" class="form-input" name="display_name" value="{{ old('display_name', $role->display_name) }}" required>@error('display_name')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="md:col-span-2"><label class="form-label" for="role-description">Deskripsi</label><textarea id="role-description" class="form-input" name="description" rows="2" placeholder="Jelaskan tugas pengguna dengan role ini">{{ old('description', $role->description) }}</textarea>@error('description')<p class="error">{{ $message }}</p>@enderror</div>
    </section>
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-bold">Permissions</h2><span x-show="dirty" x-cloak class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Unsaved Changes</span></div>
    @error('permissions')<p class="error">{{ $message }}</p>@enderror
    @foreach($groups as $group => $items)
        @php $groupNames = array_keys($items); $modules = collect($items)->groupBy(fn ($label, $name) => \Illuminate\Support\Str::before($name, '.'), true); @endphp
        <section class="card">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4"><div><p class="text-xs font-bold uppercase tracking-[.16em] text-indigo-700">{{ $group }}</p><p class="mt-1 text-xs text-slate-500">Atur akses sesuai kebutuhan tim.</p></div>@if($group !== 'Dasbor')<button type="button" class="text-xs font-semibold text-indigo-700 hover:underline" @click="toggleMany(@js($groupNames))">Pilih / batalkan semua grup</button>@endif</div>
            <div class="grid gap-5 md:grid-cols-2">
                @foreach($modules as $module => $permissions)
                    @php $moduleNames = array_keys($permissions->all()); @endphp
                    <div class="rounded-xl border border-slate-200 p-4"><div class="mb-3 flex items-center justify-between gap-2"><h3 class="font-semibold">{{ \Illuminate\Support\Str::headline(str_replace('-', ' ', $module)) }}</h3><button type="button" class="text-xs text-indigo-700 hover:underline" @click="toggleMany(@js($moduleNames))">Pilih semua</button></div>
                        <div class="space-y-2">@foreach($permissions as $name => $label)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg p-2 hover:bg-slate-50"><input type="checkbox" name="permissions[]" value="{{ $name }}" :checked="selected.includes('{{ $name }}')" @change="toggle('{{ $name }}', $event.target.checked)" class="mt-1 rounded border-slate-300 text-indigo-600"><span><strong class="block text-sm font-medium {{ isset($descriptions[$name]) ? 'text-amber-800' : '' }}">{{ $label }}</strong>@if(isset($descriptions[$name]))<small class="mt-1 block text-xs text-slate-500">{{ $descriptions[$name] }}</small>@endif</span></label>
                        @endforeach</div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
    <div class="flex flex-wrap items-center gap-3"><button type="submit" class="btn-primary">Simpan Perubahan</button><a wire:navigate href="{{ route('admin.users.index', ['tab' => 'roles']) }}" class="btn-secondary">Batal</a>
        @if($role->exists && ! $role->is_system && $role->users()->count() === 0)<button type="submit" form="delete-role" class="ml-auto text-sm font-semibold text-red-700">Hapus Role</button>@endif
    </div>
</form>
@if($role->exists && ! $role->is_system && $role->users()->count() === 0)<form id="delete-role" method="post" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="Hapus role ini secara permanen?">@csrf @method('DELETE')</form>@endif
@endsection
