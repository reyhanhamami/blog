// Client preview uses the same sRGB/WCAG calculations and audited shades as PHP.
export const validColor = value => typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value);
export function contrastRatio(a, b) {
    const luminance = hex => {
        const rgb = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255).map(c => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4);
        return .2126 * rgb[0] + .7152 * rgb[1] + .0722 * rgb[2];
    };
    const x = luminance(a), y = luminance(b);
    return (Math.max(x, y) + .05) / (Math.min(x, y) + .05);
}
export const contrastPairs = [
    ['heading', 'background', 'Heading / Background', 3],
    ['text', 'background', 'Body Text / Background', 4.5],
    ['text-muted', 'background', 'Muted Text / Background', 4.5],
    ['text', 'surface', 'Body Text / Card', 4.5],
    ['heading', 'surface', 'Heading / Card', 3],
    ['text-muted', 'surface', 'Muted Text / Card', 4.5],
    ['text', 'surface-alt', 'Body Text / Alternative Surface', 4.5],
    ['heading', 'surface-alt', 'Heading / Alternative Surface', 3],
    ['header-text', 'header-bg', 'Navigation / Header', 4.5],
    ['footer-text', 'footer-bg', 'Footer Text / Footer', 4.5],
    ['footer-cta-text', 'footer-cta-bg', 'Newsletter Text / Newsletter', 4.5],
    ['button-text', 'button-bg', 'Button Text / Button', 4.5],
    ['button-text', 'primary-hover', 'Button Text / Hover', 4.5],
    ['button-text', 'primary', 'Badge Text / Primary', 4.5],
    ['on-secondary', 'secondary', 'Secondary Text / Secondary', 4.5],
    ['link', 'background', 'Link / Background', 4.5],
    ['link-hover', 'background', 'Link Hover / Background', 4.5],
    ['focus', 'surface', 'Focus / Surface', 3, false],
    ['input-border', 'surface', 'Input Border / Surface', 3, false],
    ['accent', 'background', 'Accent / Background (judul besar)', 3, false],
    ['footer-cta-accent', 'footer-cta-bg', 'Accent / Newsletter (judul besar)', 3],
];

export function publicThemeEditor(wire, defaults, presets, shades) {
    return {
        colors: wire.entangle('colors'), defaults, presets, shades, original: {},
        init() {
            this.original = JSON.parse(JSON.stringify(this.colors));
            this.unloadHandler = event => { if (this.dirty) { event.preventDefault(); event.returnValue = ''; } };
            this.navigateHandler = async event => {
                if (!this.dirty) return;
                event.preventDefault();
                const Swal = (await import('sweetalert2')).default;
                const result = await Swal.fire({ title: 'Perubahan tema belum disimpan', text: 'Keluar tanpa menyimpan tema?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Keluar', cancelButtonText: 'Tetap di sini' });
                if (result.isConfirmed) { this.original = JSON.parse(JSON.stringify(this.colors)); window.Livewire.navigate(event.detail.url); }
            };
            window.addEventListener('beforeunload', this.unloadHandler);
            document.addEventListener('livewire:navigate', this.navigateHandler);
        },
        destroy() {
            window.removeEventListener('beforeunload', this.unloadHandler);
            document.removeEventListener('livewire:navigate', this.navigateHandler);
        },
        valid: validColor,
        get dirty() { return Object.keys(this.defaults).some(key => this.colors[key] !== this.original[key]); },
        get allValid() { return Object.keys(this.defaults).every(key => validColor(this.colors[key])); },
        get previewStyle() {
            const safe = Object.fromEntries(Object.entries(this.defaults).map(([key, fallback]) => [key, validColor(this.colors[key]) ? this.colors[key] : fallback]));
            const entries = Object.entries(safe).map(([key, value]) => `--public-${key}:${value}`);
            for (const [name, [key, original]] of Object.entries(this.shades)) {
                const value = '#' + [1, 3, 5].map(i => Math.max(0, Math.min(255, parseInt(safe[key].slice(i, i + 2), 16) + parseInt(original.slice(i, i + 2), 16) - parseInt(this.defaults[key].slice(i, i + 2), 16))).toString(16).padStart(2, '0')).join('') + original.slice(7);
                entries.push(`--public-${name}:${value}`);
            }
            return entries.join(';') + ';overflow:hidden;border:1px solid var(--public-border);border-radius:12px;background:var(--public-background);color:var(--public-text)';
        },
        get checks() {
            if (!this.allValid) return [];
            return contrastPairs.map(([foreground, background, label, target, blocking = true]) => {
                const ratio = contrastRatio(this.colors[foreground], this.colors[background]);
                const recommended = contrastRatio('#000000', this.colors[background]) >= contrastRatio('#ffffff', this.colors[background]) ? '#000000' : '#ffffff';
                return { foreground, background, label, target, ratio, recommended, critical: blocking && ratio < 1.5 };
            });
        },
        get critical() { return this.checks.filter(check => check.critical); },
        changed() { /* colors are deferred through Livewire entangle until Save. */ },
        set(key, value) { this.colors[key] = value; },
        preset(name) { if (this.presets[name]) this.colors = { ...this.presets[name] }; },
        cancel() { this.colors = { ...this.original }; },
        async resetAll() {
            const Swal = (await import('sweetalert2')).default;
            const result = await Swal.fire({ title: 'Kembalikan ke Tema Default?', text: 'Hanya warna tema pada form ini yang direset. Klik Simpan Tema untuk menerapkan.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Reset warna', cancelButtonText: 'Batal' });
            if (result.isConfirmed) this.colors = { ...this.defaults };
        },
        async saved() {
            this.original = JSON.parse(JSON.stringify(this.colors));
            const Swal = (await import('sweetalert2')).default;
            await Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Pengaturan tampilan website berhasil diperbarui.', showConfirmButton: false, timer: 3000 });
        },
    };
}
