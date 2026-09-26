@extends('admin.layout')
@section('title', ($item->exists ? 'Edit ' : 'Buat ').$config['label'])
@section('content')
<a wire:navigate href="{{ route('admin.'.$module.'.index') }}" class="text-sm text-indigo-700">← Kembali</a><h1 class="my-6 text-3xl font-bold">{{ $item->exists ? 'Edit' : 'Buat' }} {{ $config['label'] }}</h1>
<form method="post" action="{{ $item->exists ? route('admin.'.$module.'.update', $item->id) : route('admin.'.$module.'.store') }}" class="card max-w-3xl space-y-5">@csrf @if($item->exists) @method('PATCH') @endif
@foreach($config['fields'] as $field => $type)
<div><label class="form-label" for="{{ $field }}">{{ ucfirst(str_replace('_', ' ', $field)) }} @if(in_array($field, ['name','title','youtube_id','status'])) * @endif</label>
@if($type === 'textarea')<textarea class="form-input" name="{{ $field }}" id="{{ $field }}" rows="4">{{ old($field, $item->$field) }}</textarea>
@elseif($type === 'checkbox')<label class="flex items-center gap-2"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $item->$field ?? true))> Aktif</label>
@elseif($type === 'category')<select class="form-input" data-search-select name="{{ $field }}" id="{{ $field }}"><option value="">Tanpa induk</option>@foreach($parents as $parent)@if($parent->id !== $item->id)<option value="{{ $parent->id }}" @selected(old($field, $item->$field) == $parent->id)>{{ $parent->name }}</option>@endif @endforeach</select>
@elseif($type === 'status')<select class="form-input" data-search-select name="{{ $field }}" id="{{ $field }}">@foreach(['draft','published'] as $status)<option value="{{ $status }}" @selected(old($field, $item->$field ?: 'draft') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
@else<input class="form-input" id="{{ $field }}" name="{{ $field }}" type="{{ $type === 'youtube' ? 'text' : $type }}" value="{{ old($field, $item->$field) }}" @if(in_array($field, ['name','title','youtube_id'])) required @endif @if(in_array($field, ['name','title'])) data-slug-source @endif>@endif
@error($field)<p class="error">{{ $message }}</p>@enderror</div>
@endforeach
<div><label class="form-label" for="slug">Slug</label><input class="form-input" id="slug" name="slug" value="{{ old('slug', $item->slug) }}" data-slug-target>@error('slug')<p class="error">{{ $message }}</p>@enderror</div>
@if($module === 'learning-paths' && $item->exists)<a wire:navigate href="{{ route('admin.learning-paths.items.index', $item) }}" class="btn-secondary">Kelola materi →</a>@endif
@if($module === 'courses' && $item->exists)<a wire:navigate href="{{ route('admin.courses.content.index', $item) }}" class="btn-secondary">Kelola modul & pelajaran →</a>@endif
@if($module === 'quizzes' && $item->exists)<a wire:navigate href="{{ route('admin.quizzes.questions.index', $item) }}" class="btn-secondary">Kelola pertanyaan →</a>@endif
<div class="flex gap-3 border-t border-slate-100 pt-5"><button class="btn-primary">{{ $item->exists ? 'Simpan perubahan' : 'Buat '.$config['label'] }}</button><a wire:navigate href="{{ route('admin.'.$module.'.index') }}" class="btn-secondary">Batal</a></div>
</form>
@endsection