@php
    $referenceRows = old('references', $post->sources->map(fn ($source) => ['id' => $source->id, 'title' => $source->title, 'url' => $source->url])->all());
    if (!$referenceRows) $referenceRows = [['title' => '', 'url' => '']];
@endphp
<section class="card space-y-4" data-references>
    <div><h2 class="text-lg font-semibold">Referensi</h2><p class="mt-1 text-xs text-slate-500">Sumber HTTP/HTTPS yang relevan akan tampil pada artikel dan dihitung sebagai sumber eksternal.</p></div>
    <input type="hidden" name="references_present" value="1">
    <div class="space-y-3" data-reference-list>
        @foreach($referenceRows as $index => $reference)
            <div class="rounded-lg border border-slate-200 p-3 space-y-3" data-reference-row>
                @if(!empty($reference['id']))<input type="hidden" name="references[{{ $index }}][id]" value="{{ $reference['id'] }}">@endif
                <div class="flex items-center justify-between gap-3"><h3 class="text-sm font-semibold" data-reference-label>Sumber {{ $loop->iteration }}</h3><button type="button" class="text-xs text-red-700 hover:underline" data-reference-remove>Hapus</button></div>
                <div><label class="form-label">Judul</label><input class="form-input" name="references[{{ $index }}][title]" maxlength="191" value="{{ $reference['title'] ?? '' }}" placeholder="Dokumentasi Docker">@error('references.'.$index.'.title')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="form-label">URL</label><input class="form-input" type="url" name="references[{{ $index }}][url]" maxlength="2048" value="{{ $reference['url'] ?? '' }}" placeholder="https://docs.docker.com/">@error('references.'.$index.'.url')<p class="error">{{ $message }}</p>@enderror</div>
            </div>
        @endforeach
    </div>
    <button type="button" class="btn-secondary" data-reference-add>+ Tambah Referensi</button>
</section>
