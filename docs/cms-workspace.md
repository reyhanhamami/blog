# Collapsible CMS sidebar dan analyzer artikel

Implementasi mempertahankan warna, typography, permission menu, active-route matching, algoritma analisis, serta controller/alur simpan existing. Tidak ada migration aplikasi baru.

## Sidebar

Layout bersama ada di `resources/views/admin/layout.blade.php`, dengan icon SVG di `admin/partials/nav-icon.blade.php` dan lifecycle di `resources/js/admin/workspace.js`.

- Expanded: 256px, mengikuti lebar existing. Collapsed: 72px.
- Satu toggle di topbar, label/tooltip dinamis, `aria-expanded`, keyboard focus, dan transisi 200ms. Reduced motion dihormati.
- Semua menu tetap berasal dari `config/cms_navigation.php`; konfigurasi permission dan active state tidak diubah. Navigasi existing tidak mempunyai nested submenu.
- Collapsed menampilkan icon, compact brand BI, serta tooltip pada hover/focus. Tooltip berada di luar area scroll sidebar.
- Preferensi manual disimpan sebagai `cms-sidebar-state` di localStorage. Storage yang tidak tersedia tidak menyebabkan error.
- Default expanded mulai 1440px; default collapsed pada 1024–1439px. Di bawah 1024px, sidebar menjadi drawer mobile, dengan backdrop, Escape, focus trap, dan main content inert selama menu terbuka.
- Lebar konten seluruh CMS berasal dari satu CSS variable `--cms-sidebar-width`.

## Workspace artikel dan analyzer

Create/edit memakai form dan satu instance analyzer yang sama. Container query dan ResizeObserver menggunakan lebar workspace sesudah sidebar dan padding CMS.

- Workspace minimal 1024px: grid `minmax(0, 1fr) 350px`, gap 24px. Kolom editor minimal 650px.
- Workspace lebih sempit: editor satu kolom; analyzer tertutup sampai tombol **SEO & AEO** dibuka. Analyzer tidak menjadi blok di bawah form.
- Mode inline: sticky 16px dari atas viewport, independent scrolling, dan tabs sticky. Tinggi panel dihitung terhadap posisi panel dan tinggi save bar, diperbarui melalui resize/scroll events dan requestAnimationFrame.
- Drawer laptop: panel kanan maksimal 380px menggunakan native popover top layer agar tidak terpotong container. Save bar tetap dapat diklik di bawahnya.
- Drawer tablet/mobile: native modal dialog, backdrop, focus confinement, tombol Tutup, Escape, dan pengembalian fokus ke tombol buka. Pada smartphone lebarnya hampir penuh.
- Panel tetap berada dalam DOM saat tertutup. Listener dan hasil SEO/Readability/AEO existing tetap berjalan; membuka/menutup tidak menginisialisasi ulang analyzer.
- Klik saran yang menuju field menutup drawer sebelum field menerima fokus. Listener/observer dibersihkan saat Livewire meninggalkan halaman.
- Modal taxonomy/media/editor memakai native top layer, tanpa z-index ekstrem. Toolbar tetap wrapping/internal scroll sesuai ukuran.

QA code block menemukan dua masalah existing yang diperbaiki: dialog editor ditutup sebelum caret dipulihkan agar editor tidak inert, dan dependency Prism `markup-templating` dimuat sebelum grammar PHP.

## Hasil QA pada 12 Oktober 2026

Chromium headless/Playwright menggunakan SQLite QA terpisah, bukan database project. Kedua route create/edit diperiksa pada semua ukuran berikut.

| Viewport | Sidebar default | Analyzer default | Hasil |
|---|---|---|---|
| 1920 × 1080 | Expanded | Sticky kanan | Lulus |
| 1440 × 900 | Expanded | Sticky kanan | Lulus |
| 1366 × 768 | Collapsed | Sticky kanan | Lulus |
| 1280 × 800 | Collapsed | Sticky kanan | Lulus |
| 1024 × 768 | Collapsed | Drawer kanan | Lulus |
| 768 × 1024 | Mobile drawer | Modal kanan | Lulus |
| 390 × 844 | Mobile drawer | Modal hampir penuh | Lulus |

Expand manual pada 1280px mengalihkan analyzer ke drawer dan mempertahankan ruang editor. Pada 1366px expanded masih memberi kolom editor sekitar 672px; collapsed memberi sekitar 842px. Pada 1280px collapsed memberi sekitar 770px. Analyzer inline selalu 350px.

Interaksi terverifikasi: toggle, tooltip, navigasi/reload/Back/Forward, drawer dan Escape/fokus, analisis realtime saat tertutup, kategori/tag/topik benar-benar dibuat dan terpilih, Tom Select, Media Library, heading, tabel, penyisipan code block, create/update/Batal, dan tidak ada horizontal overflow. Pemeriksaan jumlah instance tidak menunjukkan duplikasi analyzer.

Semua command pemeriksaan lulus:

- `php artisan optimize:clear`
- `composer validate`
- `php vendor/bin/pint --test --quiet`
- `php artisan test --compact --no-ansi`: **159 tests, 1348 assertions**.
- `node --test tests/js/*.test.js`: **11 tests**.
- `npm.cmd run build`: lulus; digunakan karena wrapper `npm.ps1` diblokir execution policy Windows.
- Browser suite: lulus, tanpa error JavaScript atau request lokal gagal pada run final.

Batasan visual: jaringan QA memblokir Google Fonts existing. Run final memakai `CMS_QA_OFFLINE_FONTS=1`, yang mengganti request stylesheet font dengan CSS kosong hanya pada browser test. Aplikasi dan konfigurasi typography tidak diubah. Screenshot menggunakan fallback font. Tidak ada issue implementasi yang masih ditemukan pada cakupan QA tersebut.

Hasil JSON dan screenshot ada di `storage/app/cms-workspace-qa/` (artefak lokal yang diabaikan Git).

## Menjalankan ulang browser QA

Gunakan dua terminal dengan environment SQLite yang sama. Tidak perlu mengubah `.env`:

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=Join-Path (Get-Location) 'storage/app/cms-workspace-qa.sqlite'
$env:SESSION_DRIVER='file'
$env:CACHE_STORE='array'
$env:APP_ENV='testing'
$env:APP_URL='http://127.0.0.1:8000'
if (-not (Test-Path -LiteralPath $env:DB_DATABASE)) { New-Item -ItemType File -Path $env:DB_DATABASE | Out-Null }
php tests/browser/seed-cms-workspace.php
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Di terminal kedua, tetapkan `PLAYWRIGHT_MODULE` ke `playwright-core/index.mjs` yang sudah tersedia; bila perlu tetapkan `CMS_QA_CHROMIUM` ke executable Chromium existing. Lalu jalankan:

```powershell
$env:CMS_QA_OFFLINE_FONTS='1' # hanya bila jaringan font tidak tersedia
node tests/browser/cms-workspace.mjs
```

Fixture menolak database selain file SQLite `cms-workspace-qa.sqlite`, dan browser suite menolak server selain localhost. Akun fixture serta artikel/taxonomy QA hanya digunakan pada database terpisah tersebut.

## File yang berubah

Source: `resources/css/admin.css`, `resources/js/admin.js`, `resources/js/admin/workspace.js`, `resources/js/app.js`, `resources/js/article-editor.js`, `resources/js/article-form.js`, `resources/views/admin/layout.blade.php`, `resources/views/admin/partials/nav-icon.blade.php`, `resources/views/admin/posts/form.blade.php`, `resources/views/admin/posts/seo-analysis.blade.php`.

Test/dokumentasi: `tests/Feature/CmsWorkspaceTest.php`, `tests/browser/cms-workspace.mjs`, `tests/browser/seed-cms-workspace.php`, `docs/cms-workspace.md`.

Build: `public/build/manifest.json`, `admin-CssaZCcA.css`, `admin-DRzzrWu7.js`, `article-editor-COKbAfC5.js`, `article-form-C8UAJz49.js` di `public/build/assets/`. Empat bundle lama diganti oleh bundle hasil build ini.
