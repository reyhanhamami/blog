@php
    $imageValue = old($kind === 'featured' ? 'featured_image' : 'og_image', $kind === 'featured' ? $post->featured_image : $post->og_image);
@endphp
<div class="article-image-field" data-article-image-field="{{ $kind }}">
    <div class="article-image-card {{ $kind === 'og' ? 'article-image-card--og' : '' }}" data-image-preview-box>
        <img @if($imageValue) src="{{ $imageValue }}" @endif alt="Pratinjau {{ $kind === 'og' ? 'OG image' : 'gambar artikel' }}" data-image-preview @if(! $imageValue) hidden @endif>
        <span data-image-empty @if($imageValue) hidden @endif>Belum ada gambar dipilih</span>
    </div>
    <input type="hidden" name="{{ $kind === 'featured' ? 'featured_image' : 'og_image' }}" value="{{ $imageValue }}" data-image-value>
    <div class="mt-3 flex flex-wrap gap-2">
        <button type="button" class="btn-secondary" data-image-choose>{{ $imageValue ? 'Ganti gambar' : 'Pilih gambar' }}</button>
        <button type="button" class="btn-secondary" data-image-clear @if(! $imageValue) hidden @endif>Hapus pilihan</button>
    </div>
    @error($kind === 'featured' ? 'featured_image' : 'og_image')<p class="error" role="alert">{{ $message }}</p>@enderror
</div>
