<div class="article-editor-field" data-article-editor-field data-media-upload-url="{{ route('admin.media.store') }}">
    <label class="form-label" for="article-editor">Konten HTML</label>
    <p class="mb-2 text-xs text-slate-500">Judul artikel sudah menjadi H1 di halaman publik. Mulai bagian utama dengan H2.</p>
    <div class="article-editor-shell" data-editor-shell>
        <div class="editor-toolbar" role="toolbar" aria-label="Format artikel">
            <div class="editor-tool-group" aria-label="Riwayat">
                <button type="button" data-editor-command="undo" title="Urungkan (Ctrl+Z)" aria-label="Urungkan">↶</button>
                <button type="button" data-editor-command="redo" title="Ulangi (Ctrl+Shift+Z)" aria-label="Ulangi">↷</button>
            </div>
            <div class="editor-tool-group" aria-label="Tipe blok">
                <label class="sr-only" for="editor-block">Tipe paragraf</label>
                <select id="editor-block" data-editor-block title="Tipe paragraf" aria-label="Tipe paragraf">
                    <option value="p">Paragraf</option>
                    @foreach(range(1, 6) as $level)<option value="h{{ $level }}">Heading {{ $level }}</option>@endforeach
                </select>
            </div>
            <div class="editor-tool-group" aria-label="Format teks">
                <button type="button" data-editor-command="bold" title="Tebal (Ctrl+B)" aria-label="Tebal"><strong>B</strong></button>
                <button type="button" data-editor-command="italic" title="Miring (Ctrl+I)" aria-label="Miring"><em>I</em></button>
                <button type="button" data-editor-command="underline" title="Garis bawah" aria-label="Garis bawah"><u>U</u></button>
                <button type="button" data-editor-command="strike" title="Coret" aria-label="Coret"><s>S</s></button>
            </div>
            <div class="editor-tool-group" aria-label="Perataan">
                <button type="button" data-editor-command="align-left" title="Rata kiri" aria-label="Rata kiri">≡</button>
                <button type="button" data-editor-command="align-center" title="Rata tengah" aria-label="Rata tengah">☰</button>
                <button type="button" data-editor-command="align-right" title="Rata kanan" aria-label="Rata kanan">≡</button>
                <button type="button" data-editor-command="align-justify" title="Rata kiri kanan" aria-label="Rata kiri kanan">▤</button>
            </div>
            <div class="editor-tool-group" aria-label="Struktur">
                <button type="button" data-editor-command="unordered" title="Daftar berpoin" aria-label="Daftar berpoin">• Daftar</button>
                <button type="button" data-editor-command="ordered" title="Daftar bernomor" aria-label="Daftar bernomor">1. Daftar</button>
                <button type="button" data-editor-command="quote" title="Kutipan" aria-label="Kutipan">❝</button>
                <button type="button" data-editor-command="inline-code" title="Kode inline" aria-label="Kode inline">&lt;/&gt;</button>
            </div>
            <div class="editor-tool-group" aria-label="Sisipkan">
                <button type="button" data-editor-command="link" title="Sisipkan atau ubah tautan (Ctrl+K)" aria-label="Tautan">Tautan</button>
                <button type="button" data-editor-command="image" title="Sisipkan gambar" aria-label="Gambar">Gambar</button>
                <button type="button" data-editor-command="table" title="Sisipkan tabel 3×3" aria-label="Tabel">Tabel</button>
                <button type="button" data-editor-command="code" title="Sisipkan blok kode" aria-label="Blok kode">Kode</button>
                <button type="button" data-editor-command="youtube" title="Sisipkan YouTube" aria-label="YouTube">YouTube</button>
                <button type="button" data-editor-command="hr" title="Sisipkan garis pemisah" aria-label="Garis pemisah">Garis</button>
            </div>
            <div class="editor-tool-group" aria-label="Alat">
                <button type="button" data-editor-command="clear" title="Hapus format teks terpilih" aria-label="Hapus format">Hapus format</button>
                <button type="button" data-editor-command="source" title="Lihat HTML" aria-label="Lihat HTML">&lt;/&gt; HTML</button>
                <button type="button" data-editor-command="focus" title="Mode fokus (Esc untuk keluar)" aria-label="Mode fokus">Fokus</button>
            </div>
        </div>
        <div class="editor-context" data-editor-image-context hidden>
            <span>Gambar terpilih</span>
            <label>Ukuran <select data-editor-image-size aria-label="Ukuran gambar"><option value="small">Kecil</option><option value="medium">Sedang</option><option value="large">Besar</option><option value="full">Penuh</option></select></label>
            <label>Posisi <select data-editor-image-align aria-label="Posisi gambar"><option value="left">Kiri</option><option value="center">Tengah</option><option value="right">Kanan</option></select></label>
            <button type="button" data-editor-image-edit title="Ubah alt dan caption">Edit detail</button>
            <button type="button" data-editor-image-delete title="Hapus gambar">Hapus</button>
        </div>
        <div class="editor-context" data-editor-table-context hidden>
            <span>Tabel</span>
            @foreach(['row-above' => 'Baris atas', 'row-below' => 'Baris bawah', 'col-left' => 'Kolom kiri', 'col-right' => 'Kolom kanan', 'row-delete' => 'Hapus baris', 'col-delete' => 'Hapus kolom', 'header-toggle' => 'Header', 'table-delete' => 'Hapus tabel'] as $action => $label)
                <button type="button" data-editor-table-action="{{ $action }}" title="{{ $label }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="editor-context" data-editor-code-context hidden>
            <label>Bahasa <select data-editor-code-language aria-label="Bahasa kode">
                @foreach(['text' => 'Plain Text', 'php' => 'PHP', 'javascript' => 'JavaScript', 'typescript' => 'TypeScript', 'html' => 'HTML', 'css' => 'CSS', 'bash' => 'Bash', 'json' => 'JSON', 'sql' => 'SQL', 'python' => 'Python', 'go' => 'Go'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select></label>
            <button type="button" data-editor-code-remove title="Hapus blok kode">Hapus blok kode</button>
        </div>
        <div class="editor-context" data-editor-link-context hidden><span>Tautan</span><button type="button" data-editor-link-edit>Edit</button><button type="button" data-editor-link-open>Buka</button><button type="button" data-editor-link-unlink>Hapus tautan</button></div>
        <div class="editor-context" data-editor-youtube-context hidden><span>YouTube</span><button type="button" data-editor-youtube-edit>Edit URL</button><button type="button" data-editor-youtube-delete>Hapus video</button></div>
        <div id="article-editor" data-article-editor contenteditable="true" role="textbox" aria-label="Konten artikel" aria-multiline="true" data-placeholder="Mulai tulis artikel..." class="article-content" wire:ignore>{!! app(\App\Services\Content\HtmlSanitizer::class)->clean(old('content', $post->content)) !!}</div>
        <textarea id="editor-source" class="editor-source" data-editor-source aria-label="Sumber HTML artikel" hidden spellcheck="false"></textarea>
        <div class="editor-status"><span data-editor-count>0 kata · ±0 menit baca</span><span data-save-status>Draf tersimpan setelah menekan Simpan.</span></div>
    </div>
    <textarea class="hidden" id="content" name="content">{{ old('content', $post->content) }}</textarea>
    <div class="editor-structure" data-editor-structure aria-live="polite"></div>
    @error('content')<p class="error" role="alert">{{ $message }}</p>@enderror

    <dialog class="editor-dialog" data-editor-dialog="link" aria-label="Sisipkan tautan">
        <h3>Tautan</h3><p class="text-xs text-slate-500">Gunakan URL https://, http://, atau path internal yang diawali /.</p>
        <label>URL<input class="form-input" type="text" inputmode="url" data-editor-link-url placeholder="https://contoh.com atau /artikel"></label>
        <label>Judul tautan (opsional)<input class="form-input" data-editor-link-title maxlength="191"></label>
        <label class="editor-check"><input type="checkbox" data-editor-link-new-tab> Buka di tab baru</label>
        <p class="error" data-editor-link-error hidden></p>
        <div class="editor-dialog-actions"><button type="button" data-editor-link-remove>Hapus tautan</button><button type="button" data-editor-close>Batal</button><button type="button" class="btn-primary" data-editor-link-insert>Terapkan</button></div>
    </dialog>

    <dialog class="editor-dialog editor-image-dialog" data-editor-dialog="image" aria-label="Sisipkan gambar">
        <h3>Gambar artikel</h3>
        <div class="editor-dialog-tabs"><button type="button" data-editor-image-tab="upload" aria-pressed="true">Upload baru</button>@can('media.view')<button type="button" data-editor-image-tab="library" aria-pressed="false">Media Library</button>@endcan</div>
        <div data-editor-image-upload-pane>@can('media.upload')<label>File gambar<input class="form-input" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-editor-image-file></label>@else<p>Anda tidak memiliki izin upload media.</p>@endcan</div>
        <div data-editor-image-library-pane hidden>@include('admin.posts.media-library')</div>
        <div class="editor-image-preview"><img data-editor-image-preview alt="Pratinjau gambar" hidden></div>
        <label>Alt text<input class="form-input" data-editor-image-alt maxlength="191" placeholder="Jelaskan isi gambar"></label>
        <p class="text-xs text-amber-700" data-editor-alt-warning hidden>Alt text membantu aksesibilitas dan pemahaman mesin pencari.</p>
        <label>Caption (opsional)<input class="form-input" data-editor-image-caption maxlength="1000"></label>
        <div class="editor-dialog-grid"><label>Ukuran<select class="form-input" data-editor-image-dialog-size><option value="small">Kecil (320px)</option><option value="medium" selected>Sedang (560px)</option><option value="large">Besar (760px)</option><option value="full">Penuh</option></select></label><label>Posisi<select class="form-input" data-editor-image-dialog-align><option value="left">Kiri</option><option value="center" selected>Tengah</option><option value="right">Kanan</option></select></label></div>
        <p class="error" data-editor-image-error hidden></p>
        <div class="editor-dialog-actions"><button type="button" data-editor-close>Batal</button><button type="button" class="btn-primary" data-editor-image-insert>Sisipkan gambar</button></div>
    </dialog>

    <dialog class="editor-dialog" data-editor-dialog="code" aria-label="Sisipkan blok kode">
        <h3>Blok kode</h3><label>Bahasa<select class="form-input" data-editor-code-insert-language>@foreach(['text' => 'Plain Text', 'php' => 'PHP', 'javascript' => 'JavaScript', 'typescript' => 'TypeScript', 'html' => 'HTML', 'css' => 'CSS', 'bash' => 'Bash', 'json' => 'JSON', 'sql' => 'SQL', 'python' => 'Python', 'go' => 'Go'] as $value => $label)<option value="{{ $value }}" @selected($value === 'php')>{{ $label }}</option>@endforeach</select></label>
        <label>Kode<textarea class="form-input editor-code-input" data-editor-code-input rows="10" spellcheck="false"></textarea></label>
        <div class="editor-dialog-actions"><button type="button" data-editor-close>Batal</button><button type="button" class="btn-primary" data-editor-code-insert>Sisipkan kode</button></div>
    </dialog>
</div>
