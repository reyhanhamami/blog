<div class="article-media-library" data-media-gallery data-media-index-url="{{ route('admin.media.index') }}">
    @can('media.view')
        <label class="form-label">Cari gambar
            <input class="form-input" type="search" placeholder="Cari nama file, alt, atau caption" data-media-search>
        </label>
        <div class="editor-media-grid" data-media-results aria-live="polite"></div>
        <p class="text-sm text-slate-500" data-media-empty hidden>Belum ada gambar di Media Library.</p>
        <p class="error" data-media-error role="alert" hidden></p>
        <button type="button" class="btn-secondary mt-3" data-media-more hidden>Muat lagi</button>
    @else
        <p class="text-sm text-slate-500">Anda tidak memiliki izin melihat Media Library.</p>
    @endcan
</div>
