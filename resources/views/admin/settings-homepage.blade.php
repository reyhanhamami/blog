<section class="card space-y-6" id="homepage">
    <div><h2 class="text-xl font-semibold">Homepage</h2><p class="mt-1 text-sm text-slate-500">Konten hero di halaman utama, terpisah dari artikel unggulan.</p></div>
    <div class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Hero Homepage</h3>
        @foreach([
            'home_hero_eyebrow' => ['Eyebrow', 'Teks kecil di atas judul.'],
            'home_hero_heading_line_1' => ['Judul Baris 1', ''],
            'home_hero_heading_line_2' => ['Judul Baris 2', ''],
            'home_hero_heading_highlight' => ['Judul Highlight', ''],
        ] as $key => [$label, $help])
            <div><label class="form-label" for="{{ $key }}">{{ $label }}</label><input class="form-input" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $heroValues[$key]) }}" maxlength="100" required>@if($help)<p class="mt-1 text-xs text-slate-500">{{ $help }}</p>@endif @error($key)<p class="error" role="alert">{{ $message }}</p>@enderror</div>
        @endforeach
        <div><label class="form-label" for="home_hero_description">Deskripsi</label><textarea class="form-input" id="home_hero_description" name="home_hero_description" rows="3" maxlength="500">{{ old('home_hero_description', $heroValues['home_hero_description']) }}</textarea>@error('home_hero_description')<p class="error" role="alert">{{ $message }}</p>@enderror</div>
    </div>
    <div class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Gambar Hero</h3>
        @php $heroImage = old('home_hero_image', $heroValues['home_hero_image']); @endphp
        <div class="article-image-field" data-home-hero-image>
            <div class="article-image-card"><img @if($heroImage) src="{{ $heroImage }}" @endif alt="Pratinjau gambar hero" data-home-image-preview @if(! $heroImage) hidden @endif><span data-home-image-empty @if($heroImage) hidden @endif>Belum ada gambar. Hero memakai fallback Build Grow Together.</span></div>
            <input type="hidden" name="home_hero_image" value="{{ $heroImage }}" data-home-image-value>
            <div class="mt-3 flex flex-wrap gap-2"><button type="button" class="btn-secondary" data-home-image-choose>{{ $heroImage ? 'Ganti gambar' : 'Pilih gambar' }}</button><button type="button" class="btn-secondary" data-home-image-clear @if(! $heroImage) hidden @endif>Hapus gambar</button></div>
            @error('home_hero_image')<p class="error" role="alert">{{ $message }}</p>@enderror
        </div>
        <p class="text-xs text-slate-500">Gunakan gambar landscape dengan resolusi cukup untuk layar desktop. Hapus hanya mengosongkan referensi hero; file Media Library tidak dihapus.</p>
        <div><label class="form-label" for="home_hero_image_alt">Alt Gambar</label><input class="form-input" id="home_hero_image_alt" name="home_hero_image_alt" value="{{ old('home_hero_image_alt', $heroValues['home_hero_image_alt']) }}" maxlength="255"><p class="mt-1 text-xs text-slate-500">Jelaskan isi gambar secara singkat untuk aksesibilitas.</p>@error('home_hero_image_alt')<p class="error" role="alert">{{ $message }}</p>@enderror</div>
        <p class="text-xs text-slate-500">Logo global tetap dikelola di <a class="text-indigo-700 underline" href="#branding">Branding</a>.</p>
    </div>
    <div class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Konten Collage</h3>
        <div><label class="form-label" for="home_hero_gold_note">Catatan Emas</label><input class="form-input" id="home_hero_gold_note" name="home_hero_gold_note" value="{{ old('home_hero_gold_note', $heroValues['home_hero_gold_note']) }}" maxlength="150">@error('home_hero_gold_note')<p class="error" role="alert">{{ $message }}</p>@enderror</div>
        <div><label class="form-label" for="home_hero_black_label">Label Hitam</label><textarea class="form-input" id="home_hero_black_label" name="home_hero_black_label" rows="2" maxlength="150">{{ old('home_hero_black_label', $heroValues['home_hero_black_label']) }}</textarea><p class="mt-1 text-xs text-slate-500">Gunakan baris baru untuk memisahkan teks.</p>@error('home_hero_black_label')<p class="error" role="alert">{{ $message }}</p>@enderror</div>
        <div><label class="form-label" for="home_hero_white_notes">Catatan Putih</label><textarea class="form-input" id="home_hero_white_notes" name="home_hero_white_notes" rows="4" maxlength="500">{{ old('home_hero_white_notes', $heroValues['home_hero_white_notes']) }}</textarea><p class="mt-1 text-xs text-slate-500">Satu baris untuk satu teks. Maksimal empat item.</p>@error('home_hero_white_notes')<p class="error" role="alert">{{ $message }}</p>@enderror</div>
    </div>
    <div><h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Pencarian</h3><label class="form-label mt-3" for="home_hero_search_placeholder">Placeholder Pencarian</label><input class="form-input" id="home_hero_search_placeholder" name="home_hero_search_placeholder" value="{{ old('home_hero_search_placeholder', $heroValues['home_hero_search_placeholder']) }}" maxlength="120" required>@error('home_hero_search_placeholder')<p class="error" role="alert">{{ $message }}</p>@enderror</div>
</section>
