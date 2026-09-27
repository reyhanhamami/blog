@extends('admin.layout')
@section('title', $config['label'])
@section('content')
@php
    $canCreate = auth()->user()->can($module === 'authors' ? 'authors.manage' : $module.'.create');
    $canUpdate = auth()->user()->can($module === 'authors' ? 'authors.manage' : $module.'.update');
    $canDelete = auth()->user()->can($module === 'authors' ? 'authors.manage' : $module.'.delete');
@endphp
<div class="mb-6 flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-3xl font-bold">{{ $config['label'] }}</h1><p class="text-sm text-slate-500">Kelola data {{ strtolower($config['label']) }}.</p></div>@if($canCreate)<a wire:navigate href="{{ route('admin.'.$module.'.create') }}" class="btn-primary">+ Tambah {{ $config['label'] }}</a>@endif</div>
<form action="{{ route('admin.'.$module.'.index') }}" class="card mb-5 flex flex-wrap gap-3"><input class="form-input max-w-sm" name="q" placeholder="Cari {{ strtolower($config['label']) }}..." value="{{ request('q') }}"><button class="btn-primary">Cari</button><a wire:navigate href="{{ route('admin.'.$module.'.index') }}" class="btn-secondary">Reset</a>@if(in_array($module, ['categories','videos','quizzes','learning-paths','courses']))<a wire:navigate href="{{ route('admin.'.$module.'.index', ['trash'=>1]) }}" class="self-center text-sm text-indigo-700">Trash</a>@endif</form>
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white"><table class="w-full text-left text-sm"><thead class="bg-slate-100"><tr><th class="p-4">Nama</th><th class="p-4">Slug</th><th class="p-4">Diperbarui</th><th class="p-4">Aksi</th></tr></thead><tbody>
@forelse($items as $item)<tr class="border-t border-slate-100"><td class="p-4 font-semibold">{{ $item->title ?? $item->name }}</td><td class="p-4 text-slate-500">{{ $item->slug }}</td><td class="p-4 text-slate-500">{{ $item->updated_at?->timezone('Asia/Jakarta')->format('d M Y') }}</td><td class="p-4"><div class="flex gap-3">@if(method_exists($item, 'trashed') && $item->trashed()) @if($canUpdate)<form action="{{ route('admin.'.$module.'.restore', $item->id) }}" method="post">@csrf<button class="text-indigo-700">Pulihkan</button></form>@endif @else @if($canUpdate)<a wire:navigate href="{{ route('admin.'.$module.'.edit', $item->id) }}" class="text-indigo-700">Edit</a>@endif @if($canDelete)<form action="{{ route('admin.'.$module.'.destroy', $item->id) }}" method="post" data-confirm="Hapus {{ strtolower($config['label']) }} ini?">@csrf @method('DELETE')<button class="text-red-700">Hapus</button></form>@endif @endif</div></td></tr>
@empty<tr><td colspan="4" class="p-10 text-center text-slate-500">Belum ada {{ strtolower($config['label']) }}. @if($canCreate)<a wire:navigate href="{{ route('admin.'.$module.'.create') }}" class="text-indigo-700 underline">Buat sekarang</a>.@endif</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $items->links() }}</div>
@endsection
