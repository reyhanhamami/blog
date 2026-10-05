<dialog class="editor-dialog article-picker-dialog" data-home-hero-picker data-media-upload-url="{{ route('admin.media.store') }}" aria-label="Pilih gambar hero">
    <h3>Gambar Hero Homepage</h3>
    <div class="editor-dialog-tabs" role="group" aria-label="Sumber gambar">
        @can('media.upload')<button type="button" data-home-picker-tab="upload" aria-pressed="false">Upload Baru</button>@endcan
        @can('media.view')<button type="button" data-home-picker-tab="library" aria-pressed="false">Media Library</button>@endcan
        <button type="button" data-home-picker-tab="url" aria-pressed="true">URL</button>
    </div>
    @can('media.upload')<div data-home-picker-pane="upload" hidden><label class="article-dropzone">Pilih gambar<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-home-picker-file></label><p class="mt-2 text-xs text-slate-500">JPG, PNG, WebP, atau GIF, maksimal 5 MB.</p><label class="form-label mt-3" for="home-picker-alt">Alt gambar saat upload *</label><input class="form-input" id="home-picker-alt" maxlength="191" data-home-picker-alt></div>@endcan
    @can('media.view')<div data-home-picker-pane="library" hidden>@include('admin.posts.media-library')</div>@endcan
    <div data-home-picker-pane="url"><label class="form-label" for="home-picker-url">URL gambar HTTP atau HTTPS</label><input class="form-input" id="home-picker-url" type="url" data-home-picker-url placeholder="https://example.com/hero.jpg"><button type="button" class="btn-secondary mt-2" data-home-picker-url-preview>Tampilkan preview</button></div>
    <div class="editor-image-preview article-picker-preview"><img data-home-picker-preview alt="Pratinjau gambar terpilih" hidden><span data-home-picker-empty>Belum ada gambar dipilih</span></div>
    <p class="error" role="alert" data-home-picker-error hidden></p>
    <div class="editor-dialog-actions"><button type="button" class="btn-secondary" data-home-picker-cancel>Batal</button><button type="button" class="btn-primary" data-home-picker-apply>Pilih gambar</button></div>
</dialog>
