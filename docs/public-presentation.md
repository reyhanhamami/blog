# Red White, featured image, dan sticky sidebar

Perubahan ini dibatasi pada preset warna dan presentasi publik. Tidak mengubah schema/database aplikasi, konten/source gambar artikel, SEO/AEO, quiz, course, desain CMS, atau deployment cPanel.

## Preset Red White

`PublicTheme::presets()` kini menambahkan Red White dengan seluruh 26 token existing. Definisi Besofton Classic, Modern Blue, dan Forest Green tidak diubah. Pemilihan preset hanya mengisi form/preview; Simpan Tema menerapkan warna melalui Settings dan invalidasi cache existing. Reset dan custom color tetap melalui editor existing. Seeder tetap `firstOrCreate`, sehingga deploy `--seed` mempertahankan Red White maupun warna custom yang telah disimpan.

| Token | Red White |
| --- | --- |
| primary | #C62828 |
| primary-hover | #A91D1D |
| secondary | #8E1B1B |
| on-secondary | #FFFFFF |
| accent | #E53935 |
| background | #FAFAFA |
| surface | #FFFFFF |
| surface-alt | #FFF5F5 |
| border | #E5E5E5 |
| input-border | #A36A6A |
| focus | #A91D1D |
| heading | #171717 |
| text | #262626 |
| text-muted | #666666 |
| link | #A91D1D |
| link-hover | #8E1B1B |
| eyebrow | #A91D1D |
| header-bg | #FFFFFF |
| header-text | #171717 |
| footer-bg | #FFF5F5 |
| footer-text | #262626 |
| footer-cta-bg | #8E1B1B |
| footer-cta-text | #FFFFFF |
| footer-cta-accent | #FFFFFF |
| button-bg | #C62828 |
| button-text | #FFFFFF |

Seluruh 21 pasangan kontras yang diperiksa editor memenuhi targetnya pada Red White. Contoh: teks tombol/primary 5.62:1, teks tombol/hover 7.29:1, muted/background 5.50:1, newsletter 9.04:1. Target teks normal 4.5:1, heading besar/indikator UI 3:1. Ini menguji pasangan token editor; warna yang tertanam di gambar dan palette syntax highlighting tetap seperti sumbernya.

## Audit gambar asli dan penyebab area hitam

Gambar yang diberikan pengguna diunduh read-only dari [file WebP production](https://blog.besofton.id/uploads/2026/10/O3CZJeloIgtDqqGb4vuecnNaG0KoEPaFidkTLtbA.webp), kemudian dilihat langsung dan diperiksa pikselnya. Ukuran asli **1200 x 630**, tanpa pita hitam pada bagian bawah; tidak ada piksel gelap dengan semua kanal di bawah 25 pada 10% baris terbawah. SHA-256 salinan asli: `c197415148adb322ce090c043df56ff3a58d0245681faac838c1c05ad8893a54`.

CSS sebelumnya memberi wrapper `.featured-visual` warna gelap dan membiarkannya stretch mengikuti tinggi kolom teks. Gambar dibatasi `max-height: 400px`, `min-height: 310px`, dan `object-fit: cover`; pada mobile juga dipaksa setinggi 260px. Bila kolom lebih tinggi, wrapper menyisakan area gelap. Rasio berbeda juga dapat terpotong oleh cover.

Gambar utama sekarang memakai ukuran auto, max-width 100%, object-fit contain, serta batas tinggi responsif. Area media memesan tinggi responsif 310–440px desktop dan 260px mobile agar gambar lambat tidak menggeser section saat selesai dimuat. Rasio asli dan seluruh sudut gambar tetap tampak. Karena mempertahankan grid 55%/45% dan membatasi portrait agar tidak membuat section terlalu tinggi, sisa ruang diisi salinan gambar yang sama dengan blur dan opacity rendah. Salinan tersebut dekoratif (`alt=""`, `aria-hidden="true"`) dan terpisah dari gambar utama; hanya lapisan dekoratif memakai cover. Tidak ada edit/crop/re-encode terhadap file asli.

Background wrapper kini surface alternatif tema. Placeholder ilustrasi ketika artikel tidak memiliki gambar tetap memakai desain branded existing. Satu shade gelap khusus wrapper yang tidak dipakai lagi dihapus dari `PublicThemeDefaults` (88 shade tersisa, 26 token utama tetap). Slider memakai autoHeight dan slide align-start agar tinggi mengikuti slide aktif, termasuk saat judul/ringkasan berbeda panjang. URL, alt teks utama, urutan artikel, dan pemilihan featured post tidak diubah.

## Sticky sidebar

Sebelumnya sticky ditempatkan pada `.article-sidebar-inner`, tetapi `.article-layout` menggunakan `align-items: start`. Akibatnya parent `.article-sidebar` hanya setinggi isinya, sehingga sticky tidak mempunyai ruang gerak.

Parent sidebar sekarang `align-self: stretch`, mengikuti tinggi kolom artikel. Inner tetap CSS sticky, dengan offset tinggi header aktual ditambah 24px. Max-height viewport dikurangi header dan gap; TOC panjang mempunyai scroll internal. Parent grid membatasi perjalanan sticky sehingga sidebar berhenti pada batas article layout, sebelum footer. Parent publik tidak diberi overflow/transform baru yang menghalangi sticky.

ResizeObserver mengukur tinggi header saat branding/font/viewport berubah dan memperbarui satu CSS variable. Tidak ada scroll listener baru untuk sidebar; gerakan sticky tetap ditangani CSS. Scroll-margin heading TOC menggunakan offset yang sama agar judul tidak tertutup header. Pembentukan TOC dan navigasi existing dipertahankan; tidak menambahkan fitur active-section baru.

Pada lebar <=900px sidebar kanan disembunyikan dan TOC mobile tetap berada pada alur dokumen normal, sesuai breakpoint existing. Desktop 1024px ke atas diuji sticky. Perubahan hanya berada pada bundle CSS/JS publik.

## File yang diubah

- `app/Support/PublicTheme.php`: preset Red White.
- `app/Support/PublicThemeDefaults.php`: menghapus satu shade wrapper gelap yang sudah tidak dipakai.
- `resources/views/public/partials/home-featured.blade.php`: gambar utama dan dekorasi soft-fill memakai source yang sama.
- `resources/css/public.css`: ukuran media/slider, contain, soft-fill, sticky/sidebar overflow, dan offset TOC.
- `resources/js/public-home.js`: autoHeight slider.
- `resources/js/public.js`: pengukuran tinggi header dengan ResizeObserver dan cleanup navigasi.
- `tests/Feature/PublicPresentationTest.php`: 26 token/kontras, save/seed, CMS isolation, source gambar/dekorasi.
- `tests/browser/public-theme.mjs`: empat preset dan pemeriksaan console.
- `tests/browser/public-presentation.mjs`: rasio gambar, file production asli, lazy loading, sticky, TOC, footer, header resize.
- `tests/browser/seed-public-presentation.php`: fixture khusus database SQLite QA existing; tidak memakai DemoSeeder.
- `docs/public-presentation.md` dan `docs/public-theme.md`: laporan/pembaruan referensi preset.
- `public/build/manifest.json` serta bundle publik hasil build: `public-wrHtM4aC.css`, `public-BpueEmd8.js`, `public-home-C8ZRAM72.js`. Bundle admin tidak berubah.

## Verifikasi dan batas QA

Browser Chromium/Playwright yang sudah terpasang digunakan pada viewport 1920, 1440, 1366, 1024, 768, dan 390px. Tidak ada dependency aplikasi baru.

- Empat preset: 264 kombinasi route/viewport/preset lulus; preview, picker/HEX, save, reset, custom input, validasi, dan isolasi CMS teruji.
- Gambar: 30 kombinasi viewport/rasio lulus, mencakup landscape, square, portrait, wide, dan byte asli gambar production. Box gambar mempertahankan rasio intrinsik dan berada seluruhnya dalam wrapper. Tidak ada background wrapper hitam.
- Sidebar: 12 kombinasi viewport/TOC pendek-panjang lulus; sticky mengikuti scroll di desktop, scroll internal berfungsi, TOC tidak tertutup header, tidak melewati article layout/footer, dan mobile normal.
- Gambar yang ditunda pemuatannya: perubahan tinggi section featured **0px** pada skenario QA 1440px.
- Header diubah menjadi 133px pada viewport 1440x480: sidebar berada pada top 157px, tinggi 299px, dan bottom 456px; tidak melewati viewport.
- Kedua suite browser selesai tanpa page error/console error atau horizontal overflow. Hash gambar asli sebelum dan sesudah identik.
- Screenshot ditinjau langsung untuk gambar asli desktop/mobile, portrait desktop, serta sidebar desktop/mobile. Artefak lokal berada di `storage/app/public-presentation-qa/` dan `storage/app/public-theme-qa/`.

Font Google diblokir untuk QA offline dan menggunakan font fallback lokal; thumbnail eksternal dimock pada suite tema. CLS seluruh situs dengan font/network production, browser selain Chromium, dan deployment hosting belum diperiksa langsung. Tidak ada deployment production yang dijalankan. Perubahan warna admin tetap runtime tanpa build ulang; build normal satu kali tetap diperlukan untuk source CSS/JS baru dan sudah disertakan.

Hasil pemeriksaan akhir:

| Perintah | Hasil |
| --- | --- |
| php artisan optimize:clear | Berhasil |
| composer validate | composer.json valid |
| php vendor/bin/pint --test --quiet | Lulus |
| php artisan test --compact --no-ansi | 234 test lulus, 2.161 assertion |
| Tes fitur presentasi baru | 3 test lulus, 111 assertion |
| npm.cmd run build | Berhasil, 165 modul; CSS publik 71.26 kB / gzip 14.17 kB |
| git diff --check | Bersih |
| Browser empat preset | 264 kombinasi lulus |
| Browser gambar/sidebar | 42 kombinasi lulus, ditambah lazy-load dan header-resize |

Tidak ada blocker yang ditemukan pada kasus yang diuji. Browser/font/network production di luar batas QA di atas belum dinyatakan lulus.
