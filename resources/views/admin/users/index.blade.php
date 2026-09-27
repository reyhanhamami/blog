@extends('admin.layout')
@section('title', 'Users & Access')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div><p class="text-sm font-semibold text-indigo-700">MANAJEMEN</p><h1 class="text-3xl font-bold">Users & Access</h1><p class="mt-2 text-sm text-slate-500">Kelola akun pengguna dan akses ke Besofton Insights CMS.</p></div>
    @if($tab === 'users') @can('users.create')<a wire:navigate href="{{ route('admin.users.create') }}" class="btn-primary">+ Tambah User</a>@endcan
    @else @can('roles.manage')<a wire:navigate href="{{ route('admin.roles.create') }}" class="btn-primary">+ Tambah Role</a>@endcan @endif
</div>
<nav class="mb-6 flex gap-2 border-b border-slate-200" aria-label="Users & Access">
    @can('users.view')<a wire:navigate href="{{ route('admin.users.index') }}" class="border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === 'users' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500' }}" @if($tab === 'users') aria-current="page" @endif>Users</a>@endcan
    @can('roles.view')<a wire:navigate href="{{ route('admin.users.index', ['tab' => 'roles']) }}" class="border-b-2 px-4 py-3 text-sm font-semibold {{ $tab === 'roles' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500' }}" @if($tab === 'roles') aria-current="page" @endif>Roles & Permissions</a>@endcan
</nav>
@if($tab === 'users')
    <form method="get" action="{{ route('admin.users.index') }}" class="card mb-5 grid gap-3 md:grid-cols-[1fr_180px_180px_auto_auto]">
        <label class="sr-only" for="users-search">Cari pengguna</label><input id="users-search" class="form-input" name="q" value="{{ request('q') }}" placeholder="Cari pengguna...">
        <label class="sr-only" for="users-role">Role</label><select id="users-role" class="form-input" name="role"><option value="">Semua role</option>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->display_name }}</option>@endforeach</select>
        <label class="sr-only" for="users-access">Akses</label><select id="users-access" class="form-input" name="access"><option value="">Semua akses</option><option value="cms" @selected(request('access') === 'cms')>CMS Access</option><option value="public" @selected(request('access') === 'public')>Public Only</option></select>
        <button type="submit" class="btn-primary">Filter</button><a wire:navigate href="{{ route('admin.users.index') }}" class="btn-secondary">Reset</a>
    </form>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="p-4">User</th><th class="p-4">Role</th><th class="p-4">Access</th><th class="p-4">Created</th><th class="p-4">Actions</th></tr></thead><tbody>
        @forelse($users as $user)
        <tr class="border-t border-slate-100"><td class="p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-indigo-100 font-bold text-indigo-700">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span><span><strong class="block font-semibold">{{ $user->name }}</strong><small class="text-slate-500">{{ $user->email }}</small></span></div></td>
            <td class="p-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ $user->roleRecord?->display_name ?? ucfirst($user->role) }}</span></td>
            <td class="p-4"><span class="rounded-full px-3 py-1 text-xs font-medium {{ $user->can('cms.access') ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $user->can('cms.access') ? 'CMS Access' : 'Public Only' }}</span></td>
            <td class="p-4 text-slate-500">{{ $user->created_at?->locale('id')->translatedFormat('j M Y') }}</td>
            <td class="p-4"><div class="flex items-center gap-3">@can('users.update')<a wire:navigate href="{{ route('admin.users.edit', $user) }}" class="font-medium text-indigo-700">Edit</a>@endcan
                @can('users.delete') @if($user->id !== auth()->id() && ($user->role !== 'superadmin' || (auth()->user()->role === 'superadmin' && $superadminCount > 1)))<form method="post" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Hapus pengguna ini?">@csrf @method('DELETE')<button type="submit" class="font-medium text-red-700">Hapus</button></form>@endif @endcan</div></td>
        </tr>
        @empty<tr><td colspan="5" class="p-8 text-center text-slate-500">Tidak ada pengguna yang cocok dengan filter.</td></tr>@endforelse
        </tbody></table>
    </div>
    <div class="mt-5">{{ $users->links() }}</div>
@else
    <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
        <div class="card"><h2 class="mb-4 text-lg font-bold">Daftar Role</h2><div class="space-y-2">@foreach($roles as $role)
            <a wire:navigate href="{{ route('admin.users.index', ['tab' => 'roles', 'role' => $role->name]) }}" class="block rounded-xl border border-slate-200 p-3 hover:border-indigo-400">
                <strong class="block">{{ $role->display_name }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $role->users_count }} users · {{ $role->name === 'superadmin' ? 'Full Access' : $role->permissions_count.' permissions' }}</span>
            </a>@endforeach</div></div>
        @php $selectedRole = $roles->firstWhere('name', request('role')) ?? $roles->first(); @endphp
        <div class="card">@if($selectedRole)
            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Role detail</p><h2 class="mt-1 text-2xl font-bold">{{ $selectedRole->display_name }}</h2><p class="mt-2 text-sm text-slate-500">{{ $selectedRole->description }}</p></div>@can('roles.manage')@if($selectedRole->name !== 'superadmin')<a wire:navigate href="{{ route('admin.roles.edit', $selectedRole) }}" class="btn-secondary">Kelola permissions</a>@endif@endcan</div>
            <div class="mt-6 flex gap-6 border-t border-slate-100 pt-5 text-sm"><span><strong>{{ $selectedRole->users_count }}</strong> users</span><span><strong>{{ $selectedRole->name === 'superadmin' ? 'Semua' : $selectedRole->permissions_count }}</strong> permissions</span></div>
            @if($selectedRole->name === 'superadmin')<p class="mt-6 rounded-xl bg-indigo-50 p-4 text-sm text-indigo-800">Full System Access. Superadmin memiliki seluruh izin secara otomatis dan tidak dapat kehilangan akses karena perubahan permission.</p>@endif
        @endif</div>
    </div>
@endif
@endsection
