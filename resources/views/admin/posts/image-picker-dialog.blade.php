<dialog class="editor-dialog article-picker-dialog" data-article-media-picker data-media-upload-url="{{ route('admin.media.store') }}" aria-label="Pilih gambar artikel">
    <h3 data-picker-title>Pilih gambar</h3>
    <div class="editor-dialog-tabs" role="group" aria-label="Sumber gambar">
        @can('media.upload')<button type="button" data-picker-tab="upload" aria-pressed="true">Upload baru</button>@endcan
        @can('media.view')<button type="button" data-picker-tab="library" aria-pressed="false">Media Library</button>@endcan
        <button type="button" data-picker-tab="url" aria-pressed="false">URL</button>
    </div>
    <div data-picker-pane="upload" @cannot('media.upload') hidden @endcannot>
        @can('media.upload')<label class="article-dropzone" data-picker-dropzone>Tarik gambar ke sini atau pilih file<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-picker-file></label><p class="mt-2 text-xs text-slate-500">JPG, PNG, WebP, GIF · maksimal 5 MB. Alt text diperlukan saat upload.</p><label class="form-label mt-3" for="picker-alt">Alt text *</label><input class="form-input" id="picker-alt" maxlength="191" data-picker-alt>@endcan
    </div>
    <div data-picker-pane="library" hidden>@include('admin.posts.media-library')</div>
    <div data-picker-pane="url" hidden><label class="form-label" for="picker-url">URL gambar HTTP atau HTTPS</label><input class="form-input" type="url" id="picker-url" placeholder="https://example.com/image.jpg" data-picker-url><button type="button" class="btn-secondary mt-2" data-picker-url-preview>Tampilkan preview</button></div>
    <div class="editor-image-preview article-picker-preview"><img data-picker-preview alt="Pratinjau gambar terpilih" hidden><span data-picker-empty>Belum ada gambar dipilih</span></div>
    <p class="text-xs text-slate-500" data-picker-dimensions hidden></p>
    <p class="text-xs text-amber-700" data-picker-ratio-warning hidden>Untuk tampilan optimal saat dibagikan, gunakan gambar sekitar 1200×630 px.</p>
    <p class="error" data-picker-error role="alert" hidden></p>
    <div class="editor-dialog-actions"><button type="button" class="btn-secondary" data-picker-cancel>Batal</button><button type="button" class="btn-primary" data-picker-apply>Pilih gambar</button></div>
</dialog>
