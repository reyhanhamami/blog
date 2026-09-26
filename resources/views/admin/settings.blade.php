@extends('admin.layout')
@section('title', 'Pengaturan')
@section('content')
<h1 class="text-3xl font-bold">Pengaturan</h1><p class="mt-2 text-sm text-slate-500">Identitas dan metadata default Besofton Insights.</p>
<form action="{{ route('admin.settings.update') }}" method="post" class="card mt-6 max-w-3xl space-y-5">@csrf @method('PATCH')
@foreach(\App\Http\Controllers\Admin\SettingsController::FIELDS as $key => $label)<div><label class="form-label" for="{{ $key }}">{{ $label }}</label>@if($key === 'default_description')<textarea class="form-input" id="{{ $key }}" name="{{ $key }}" rows="3">{{ old($key, $values[$key]) }}</textarea>@else<input class="form-input" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" @if(in_array($key, ['logo_url','favicon_url'])) type="url" @endif>@endif @error($key)<p class="error">{{ $message }}</p>@enderror</div>@endforeach
<button class="btn-primary">Simpan pengaturan</button></form>
@endsection