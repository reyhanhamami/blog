@extends('admin.layout')
@section('title', 'Pengaturan')
@section('content')
<h1 class="text-3xl font-bold">Pengaturan</h1>
<p class="mt-2 text-sm text-slate-500">Identitas dan metadata default Besofton Insights.</p>

@can('settings.manage')
<form action="{{ route('admin.settings.update') }}" method="post" enctype="multipart/form-data" class="mt-6 max-w-4xl space-y-6">
    @csrf @method('PATCH')
    <section class="card space-y-5">
        <div><h2 class="text-xl font-semibold">Branding</h2><p class="mt-1 text-sm text-slate-500">Gambar baru langsung tampil sebagai pratinjau sebelum disimpan.</p></div>
        <div class="grid gap-5 md:grid-cols-2">
            <div class="min-w-0 space-y-3">
                <label class="form-label" for="logo">Logo</label>
                <div class="flex h-36 max-w-80 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <img src="{{ $logoUrl }}" data-branding-preview="logo" data-current-src="{{ $logoUrl }}" alt="Pratinjau logo saat ini" class="h-full w-full object-contain">
                </div>
                <p class="text-xs text-slate-500">{{ \App\Support\Branding::uploadedPath('logo_path') ? 'Logo khusus aktif' : ($values['logo_url'] ? 'Logo dari URL' : 'Logo default') }}</p>
                <input class="form-input" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" data-branding-upload="logo" aria-describedby="logo-help">
                <p id="logo-help" class="text-xs text-slate-500" data-branding-selected="logo">PNG, JPG, JPEG, atau WebP · maks. 4 MB · lebar min. 100 piksel.</p>
                @error('logo')<p class="error" role="alert">{{ $message }}</p>@enderror
                <label class="form-label" for="logo_url">URL logo lama (fallback)</label>
                <input class="form-input" id="logo_url" name="logo_url" type="url" value="{{ old('logo_url', $values['logo_url']) }}">
                @error('logo_url')<p class="error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div class="min-w-0 space-y-3">
                <label class="form-label" for="favicon">Favicon</label>
                <div class="flex h-36 max-w-80 items-center justify-center gap-6 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <img src="{{ $faviconUrl }}" data-branding-preview="favicon" data-current-src="{{ $faviconUrl }}" alt="Pratinjau favicon 32 piksel" class="h-8 w-8 object-contain">
                    <img src="{{ $faviconUrl }}" data-branding-preview-large="favicon" alt="Pratinjau favicon 64 piksel" class="h-16 w-16 object-contain">
                </div>
                <p class="text-xs text-slate-500">{{ \App\Support\Branding::uploadedPath('favicon_path') ? 'Favicon khusus aktif' : ($values['favicon_url'] ? 'Favicon dari URL' : 'Favicon default') }}</p>
                <input class="form-input" id="favicon" name="favicon" type="file" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico" data-branding-upload="favicon" aria-describedby="favicon-help">
                <p id="favicon-help" class="text-xs text-slate-500" data-branding-selected="favicon">PNG minimal 32×32 atau ICO · maks. 1 MB.</p>
                @error('favicon')<p class="error" role="alert">{{ $message }}</p>@enderror
                <label class="form-label" for="favicon_url">URL favicon lama (fallback)</label>
                <input class="form-input" id="favicon_url" name="favicon_url" type="url" value="{{ old('favicon_url', $values['favicon_url']) }}">
                @error('favicon_url')<p class="error" role="alert">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <section class="card space-y-5">
        @foreach(\App\Http\Controllers\Admin\SettingsController::FIELDS as $key => $label)
            @continue(in_array($key, ['logo_url', 'favicon_url']))
            <div><label class="form-label" for="{{ $key }}">{{ $label }}</label>
                @if($key === 'default_description')<textarea class="form-input" id="{{ $key }}" name="{{ $key }}" rows="3">{{ old($key, $values[$key]) }}</textarea>
                @else<input class="form-input" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}">@endif
                @error($key)<p class="error" role="alert">{{ $message }}</p>@enderror
            </div>
        @endforeach
    </section>
    <button class="btn-primary">Simpan pengaturan</button>
</form>
@else
<div class="card mt-6 max-w-3xl space-y-4">
    <div><strong class="text-sm">Logo</strong><img src="{{ $logoUrl }}" alt="Logo saat ini" class="mt-2 h-24 max-w-full object-contain"></div>
    <div><strong class="text-sm">Favicon</strong><img src="{{ $faviconUrl }}" alt="Favicon saat ini" class="mt-2 h-16 w-16 object-contain"></div>
    @foreach(\App\Http\Controllers\Admin\SettingsController::FIELDS as $key => $label)<div><strong class="text-sm">{{ $label }}</strong><p class="text-sm text-slate-600">{{ $values[$key] ?: '—' }}</p></div>@endforeach
</div>
@endcan
@endsection
