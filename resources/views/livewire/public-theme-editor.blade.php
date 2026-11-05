<section id="public-theme" class="mt-10 min-w-0" x-data="publicThemeEditor($wire, @js($defaults), @js($presets), @js($shades))" @public-theme-saved.window="saved()">
    <div class="mb-5"><h2 class="text-xl font-semibold">Tampilan Website</h2><p class="mt-1 text-sm text-slate-500">Besofton Classic mempertahankan desain saat ini. Preview menggunakan contoh komponen statis. Tema aktif berubah setelah Simpan Tema; tampilan CMS tetap terpisah.</p></div>
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <label for="public-theme-preset" class="text-sm font-semibold">Preset</label>
        <select id="public-theme-preset" class="form-input w-auto max-w-full" @change="preset($event.target.value)" wire:loading.attr="disabled" wire:target="save"><option value="">Pilih preset...</option>@foreach($presets as $name => $palette)<option value="{{ $name }}">{{ $name }}</option>@endforeach</select>
        <button type="button" class="btn-secondary" @click="resetAll()" wire:loading.attr="disabled" wire:target="save">Kembalikan ke Tema Default</button>
    </div>
    <form wire:submit="save" class="grid min-w-0 gap-6 xl:grid-cols-2" data-theme-form :data-dirty="dirty ? '1' : '0'">
        <div class="min-w-0 space-y-5">
            @foreach($groups as $group => $fields)
                <fieldset class="card min-w-0 space-y-5" wire:loading.attr="disabled" wire:target="save"><legend class="px-2 text-base font-semibold">{{ $group }}</legend>
                    @foreach($fields as $token => [$label, $default, $help])
                        <div class="min-w-0">
                            <label class="form-label" for="theme-{{ $token }}">{{ $label }}</label>
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <input type="color" class="h-11 w-12 shrink-0 cursor-pointer rounded border border-slate-300 p-1" aria-label="Pilih {{ $label }}" :value="valid(colors['{{ $token }}']) ? colors['{{ $token }}'] : defaults['{{ $token }}']" @input="set('{{ $token }}', $event.target.value)">
                                <input id="theme-{{ $token }}" class="form-input min-w-0 flex-1" style="min-width: 110px" type="text" maxlength="7" pattern="#[0-9a-fA-F]{6}" required spellcheck="false" x-model="colors['{{ $token }}']" @input="changed()" aria-describedby="theme-{{ $token }}-help theme-{{ $token }}-error" :aria-invalid="!valid(colors['{{ $token }}'])">
                                <button type="button" class="btn-secondary" @click="set('{{ $token }}', defaults['{{ $token }}'])" aria-label="Reset {{ $label }}">Reset</button>
                            </div>
                            <p id="theme-{{ $token }}-help" class="mt-1 text-xs text-slate-500">{{ $help }}</p>
                            <p x-show="!valid(colors['{{ $token }}'])" x-cloak class="error">Gunakan HEX enam digit, misalnya #E8AF4B.</p>
                            <div id="theme-{{ $token }}-error">@error('colors.'.$token)<p class="error" role="alert">{{ $message }}</p>@enderror</div>
                        </div>
                    @endforeach
                </fieldset>
            @endforeach
            @error('colors')<p class="error" role="alert">{{ $message }}</p>@enderror
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save" :disabled="!allValid || critical.length > 0"><span wire:loading.remove wire:target="save">Simpan Tema</span><span wire:loading wire:target="save">Menyimpan...</span></button>
                <button type="button" class="btn-secondary" @click="cancel()" wire:loading.attr="disabled">Batal</button>
                <span class="text-xs text-slate-500" x-show="dirty">Perubahan belum disimpan.</span>
            </div>
        </div>
        <div class="min-w-0 space-y-5 xl:sticky xl:top-6 xl:self-start">
            <div class="card min-w-0"><h3 class="mb-3 font-semibold">Preview Tema</h3>
                <div data-theme-preview :style="previewStyle" style="overflow: hidden; border: 1px solid var(--public-border); border-radius: 12px; background: var(--public-background); color: var(--public-text)">
                    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px; padding:18px; background:var(--public-header-bg); color:var(--public-header-text); border-bottom:1px solid var(--public-border)"><strong>BESOFTON</strong><span>Insights · Belajar</span></div>
                    <div style="padding:24px">
                        <small style="color:var(--public-eyebrow)">CONTOH PREVIEW</small>
                        <h4 style="margin:12px 0; font-size:28px; line-height:1.1; color:var(--public-heading)">Temukan <em style="color:var(--public-accent)">Insight Digital</em></h4>
                        <p style="margin-bottom:16px">Artikel, tutorial, dan panduan untuk belajar.</p>
                        <button type="button" style="padding:10px 18px; border:0; border-radius:24px; background:var(--public-button-bg); color:var(--public-button-text)">Jelajahi Artikel →</button>
                        <article style="margin-top:24px; padding:18px; border:1px solid var(--public-border); border-radius:8px; background:var(--public-surface)"><span style="background:var(--public-primary); color:var(--public-button-text); padding:4px 8px; border-radius:16px; font-size:12px">Artikel Pilihan</span><h5 style="margin:12px 0; font-size:20px; color:var(--public-heading)">Judul Artikel Contoh</h5><p style="color:var(--public-text-muted)">Ringkasan statis untuk memeriksa keterbacaan kartu.</p><a href="#public-theme" @click.prevent style="color:var(--public-link); text-decoration:underline">Baca selengkapnya</a></article>
                        <div style="margin-top:16px; padding:12px; background:var(--public-surface-alt); border-radius:8px">Permukaan alternatif <span style="background:var(--public-secondary); color:var(--public-on-secondary); padding:4px 8px; border-radius:16px">Aktif</span></div>
                    </div>
                    <div style="padding:22px; background:var(--public-footer-cta-bg); color:var(--public-footer-cta-text)"><strong>NEWSLETTER</strong><p><em style="color:var(--public-footer-cta-accent)">Insight terbaru</em> langsung ke email Anda.</p><div style="margin-top:12px; padding:10px; border:1px solid var(--public-input-border); border-radius:8px; background:var(--public-surface); color:var(--public-text)">email@contoh.id <span style="float:right; background:var(--public-button-bg); color:var(--public-button-text); border-radius:50%; padding:0 6px">→</span></div></div>
                    <div style="padding:18px; background:var(--public-footer-bg); color:var(--public-footer-text)">Besofton Insights · Tentang · Privasi</div>
                </div>
            </div>
            <div class="card min-w-0 space-y-3" aria-live="polite"><h3 class="font-semibold">Kontras & Keterbacaan</h3><p class="text-xs text-slate-500">WCAG AA: teks normal 4.5:1; judul besar dan indikator UI 3:1. Kontras teks kritis di bawah 1.5:1 wajib diperbaiki sebelum simpan. Peringatan lain tetap ditampilkan, termasuk pada palet Classic.</p>
                <template x-for="check in checks" :key="check.label"><div class="rounded-lg border border-slate-200 p-3 text-xs"><div class="flex flex-wrap justify-between gap-2"><strong x-text="check.label"></strong><span x-text="`${check.ratio.toFixed(2)}:1 / target ${check.target}:1`"></span></div><template x-if="check.ratio < check.target"><div><p class="mt-1 text-amber-800" x-text="check.critical ? 'Kontras kritis: perbaiki sebelum menyimpan.' : 'Kontras terlalu rendah. Tulisan atau indikator mungkin sulit dibaca.'"></p><button type="button" class="mt-2 underline" @click="set(check.foreground, check.recommended)" x-text="`Gunakan ${check.recommended} untuk ${check.foreground}`"></button></div></template></div></template>
                <p x-show="!allValid" class="text-sm text-red-700">Perbaiki format HEX untuk memeriksa kontras.</p>
            </div>
        </div>
    </form>
</section>
