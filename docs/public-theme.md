# Dynamic Public Theme — Besofton Insights

## Arsitektur

Tema disimpan pada tabel `settings` existing. Key mengikuti `theme_{nama_token_dengan_underscore}_color`, misalnya `theme_primary_color` dan `theme_footer_cta_bg_color`. Tidak ada tabel, migration, library, atau salinan logo baru.

`App\Support\PublicTheme` menyediakan default Classic, validasi HEX, normalisasi lowercase, pembacaan settings dalam satu query, fallback, warna turunan, preset, pemeriksaan kontras, dan penyimpanan transaksional. Hasil resolve dicache satu jam dengan key `public:theme`. Save membuang cache setelah transaksi; event model Setting juga membuang cache untuk perubahan/hapus theme key melalui model. Query builder SQL langsung tidak memicu event model: gunakan `Setting::putValue()` atau panggil `PublicTheme::forget()` untuk maintenance manual.

Shared `public/layout.blade.php` menginjeksi variabel CSS tervalidasi pada `<body class="public-body public-app">`. Alias CSS existing sekarang scoped pada `.public-app`, bukan `:root`. Admin tetap menggunakan `admin-app`, `admin.css`, dan `admin.js`. Preview di settings hanya mempunyai variabel inline pada kontainer contoh; tidak memasang kelas public-app atau memuat public.css ke CMS. `meta[name=theme-color]` mengikuti header background. Canonical, schema, metadata artikel, branding, dan business logic tetap memakai sumber existing.

Default inti berasal dari stylesheet existing. `config/public_theme_shades.php` menyimpan 89 variasi warna existing beserta parent semantiknya. Nilai turunan dihitung dari selisih kanal RGB terhadap default, dibatasi 0–255; saat Classic aktif hasilnya sama persis dengan literal asal, termasuk transparansi. Admin mengatur parent semantik, bukan puluhan setting yang menduplikasi fungsi. Surface kartu, gradasi hero, dan warna teks sekunder tetap mempertahankan variasi desain. Warna turunan ikut berubah saat parent diubah.

Perubahan nilai dari CMS berlaku pada request publik berikutnya, tanpa build, optimize:clear, Redis, worker, atau Node.js runtime di hosting. Build hanya diperlukan ketika source CSS/JS berubah saat deploy.

## Warna yang dapat diubah

| Token | Default Classic | Kegunaan |
| --- | --- | --- |
| primary | #e8af4b | Badge dan indikator progres |
| primary-hover | #f3c36d | Hover tombol utama |
| secondary | #0b0b0b | Tombol sekunder, chip aktif, panel gelap |
| on-secondary | #ffffff | Teks pada secondary |
| accent | #e8af4b | Highlight INSIGHTS, bentuk dan garis aksen |
| background | #f7f3eb | Background halaman dan gradasi hero |
| surface | #fffdf9 | Kartu, panel, dan input |
| surface-alt | #f9f7f3 | Panel alternatif dan callout |
| border | #ddd6c8 | Batas kartu dan pemisah |
| input-border | #a99f91 | Batas input dan kontrol filter |
| focus | #986215 | Fokus keyboard, checkbox, pilihan kuis |
| heading | #0a0a0a | Heading halaman dan kartu |
| text | #0a0a0a | Teks isi |
| text-muted | #57534e | Metadata, ringkasan, keterangan |
| link | #0a0a0a | Tautan teks dan artikel |
| link-hover | #8a5600 | Hover tautan/navigasi |
| eyebrow | #825006 | Label bagian dan keterangan emas gelap |
| header-bg | #fbf9f5 | Header desktop/mobile |
| header-text | #0a0a0a | Navigasi header |
| footer-bg | #faf8f4 | Footer navigasi |
| footer-text | #0a0a0a | Teks dan variasi keterangan footer |
| footer-cta-bg | #11100e | Newsletter CTA |
| footer-cta-text | #ffffff | Isi newsletter |
| footer-cta-accent | #e8af4b | Highlight newsletter, terpisah agar terbaca pada CTA gelap |
| button-bg | #e8af4b | Tombol utama emas, pencarian, kirim newsletter |
| button-text | #0a0a0a | Teks tombol utama dan badge |

## UI dan otorisasi

Section **Tampilan Website** pada `/admin/settings` mengikuti navigasi anchor existing. Editor Livewire terpisah dari form Branding/Homepage/SEO. Enam kelompok warna menampilkan native color picker, input HEX, petunjuk inheritance, error per field, dan Reset individual. Picker dan HEX tersinkron melalui Alpine dengan entangle deferred: perubahan lokal tidak disimpan sebelum **Simpan Tema**.

Preview statis memuat header, brand teks, heading, paragraf, tombol, kartu, tautan, secondary chip, newsletter/input, dan footer. Preset **Besofton Classic**, **Modern Blue**, dan **Forest Green** mengisi form saja. Reset seluruh tema memakai dialog konfirmasi dan tetap membutuhkan Simpan Tema. Batal memulihkan snapshot terakhir yang disimpan. Toast sukses menggunakan SweetAlert existing. Input dan tindakan dinonaktifkan selama save; Livewire menangani loading dan submit. Navigasi Livewire dan penutupan tab memperingatkan perubahan yang belum disimpan. Desktop memakai dua kolom, ukuran lebih kecil disusun vertikal.

Gate `settings.manage` diperiksa pada mount, save, dan render komponen, termasuk request Livewire langsung. Pengguna settings.view saja tetap mendapat halaman pengaturan baca saja. Backend menolak field ekstra, field hilang, CSS injection, nama warna, HEX pendek, dan format selain `#RRGGBB`. Nilai legacy invalid diganti fallback default dan dicatat dengan key saja, tanpa mencatat payload CSS.

## Public coverage dan audit warna

Semua view dengan shared public layout menerima konfigurasi yang sama: homepage, artikel/detail dan related card, kategori, tag, topik, author, pencarian/filter/pagination, video, learning path, kelas, lesson, kuis, halaman statis, akun pembaca, login, registrasi, reset password, dan halaman error publik. Feed/sitemap/XML bukan halaman UI dan tidak diberi style.

Audit `resources/css/public.css` dan public/reader/livewire Blade tidak menemukan utility warna Tailwind yang memerlukan penggantian di view publik. Refactor dilakukan pada properti warna stylesheet; spacing, ukuran, radius, gambar, dan grid existing dipertahankan.

Klasifikasi literal tersisa:

- **A — token:** background halaman/hero, surface/kartu/callout, heading/body/muted/link, badge/progres, header/menu, footer/newsletter, tombol/form/filter/pagination, border, hover, dan fokus sudah menggunakan variabel.
- **B — tetap karena fungsi:** validation error merah, pesan newsletter sukses/error, notice status; palette Prism/code toolbar/inline code; opacity bayangan netral. Ini bukan warna brand yang perlu diganti primary.
- **C — asset/ilustrasi:** frame dan tekstur kertas collage, foto/fallback artwork dan emas pada ilustrasi berlatar gelap, logo/brand mark, thumbnail fallback, serta overlay tombol video tetap memakai palette yang kompatibel. Aksen CSS hero di luar artwork, highlight utama, dan sticky note tetap dinamis. Logo, favicon, dan gambar hero tetap mengikuti Branding/Homepage existing.

QA juga menemukan dependency Prism PHP `markup-templating` belum dimuat pada halaman publik. Import dependency kini selesai sebelum highlighting; tidak mengubah isi artikel atau palette kode.

## Kontras

PHP dan preview JS menggunakan luminansi relatif sRGB yang dilinearisasi sesuai WCAG, rasio `(Lterang + .05)/(Lgelap + .05)`. Pasangan yang diperiksa mencakup heading/body/muted versus background dan surface, heading/body versus surface alternatif, header, footer, newsletter, aksen newsletter, tombol normal/hover, badge/primary, secondary, tautan, focus, input border, dan aksen heading besar.

Target AA ditampilkan per pasangan: 4.5:1 untuk teks normal; 3:1 untuk judul besar dan indikator. Rasio di bawah target menampilkan warning spesifik dan tombol rekomendasi hitam/putih yang hanya diterapkan jika diklik. Pasangan teks kritis di bawah 1.5:1 diblokir di client dan backend. Palet Classic tetap mempertahankan emas dekoratif dan input border existing, sehingga sebagian warning AA memang tampil; editor tidak mengubah pilihan secara diam-diam. Pemeriksaan ini membantu editor memilih warna, bukan sertifikasi seluruh kombinasi konten/asset sebagai WCAG AA.

## Seeder dan deploy

`PublicThemeSettingsSeeder` didaftarkan pada DatabaseSeeder dan memakai `Setting::firstOrCreate`. Deploy existing `php artisan migrate --force --seed` hanya menambahkan key yang hilang; warna custom admin tidak ditimpa. Tidak memanggil DemoSeeder, migrate:fresh, atau mengubah `.cpanel.yml`. Pengujian browser memakai database SQLite QA terpisah dan tidak mengubah database aplikasi.

## File source yang berubah

- `app/Support/PublicTheme.php`
- `app/Livewire/PublicThemeEditor.php`
- `app/Models/Setting.php`
- `config/public_theme.php`
- `config/public_theme_shades.php`
- `database/seeders/PublicThemeSettingsSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/public/layout.blade.php`
- `resources/views/admin/settings.blade.php`
- `resources/views/livewire/public-theme-editor.blade.php`
- `resources/css/public.css`
- `resources/js/admin.js`
- `resources/js/public-theme.js`
- `resources/js/public.js`
- `tests/Feature/PublicThemeTest.php`
- `tests/Unit/PublicThemeContrastTest.php`
- `tests/js/public-theme.test.js`
- `tests/browser/public-theme.mjs`
- `tests/browser/seed-public-theme.php`
- `docs/public-theme.md`

Build memperbarui `public/build/manifest.json` dan bundle hashed yang dirujuk manifest. Asset hasil build disertakan agar deployment cPanel tidak memerlukan Node runtime.

## Verifikasi

Hasil pemeriksaan pada 5 November 2026:

| Pemeriksaan | Hasil |
| --- | --- |
| php artisan optimize:clear | Berhasil |
| composer validate | composer.json valid |
| php vendor/bin/pint --test --quiet | Lulus |
| php artisan test --compact --no-ansi | 194 test lulus, 1.796 assertion |
| Tes tema khusus | 35 test lulus, 448 assertion |
| node --test tests/js/*.test.js | 13 test JavaScript lulus (2 tema, 11 SEO existing) |
| npm.cmd run build | Berhasil, 165 modul; public CSS 70,59 kB / gzip 14,05 kB |
| php artisan route:list --except-vendor | 179 route, tanpa route settings baru |
| git diff --check | Bersih |
| Chromium/Playwright | 198 kombinasi lulus: 11 halaman x 6 viewport x 3 preset |

Viewport: 1920, 1440, 1366, 1024, 768, dan 390 piksel. Halaman browser: homepage, artikel, kategori, search, video, learning path, kelas, lesson, kuis dengan opsi jawaban, akun pembaca, dan halaman statis. Editor diperiksa pada semua viewport, termasuk background preview dari form, sinkronisasi picker/HEX, preview belum aktif sebelum save, reset individual, pembatalan/reset all terkonfirmasi, invalid HEX, blokir kontras kritis, toast save tanpa reload, dan warna body CMS tetap sama. Tidak ada overflow horizontal, tombol dengan teks sama persis dengan background, atau error JavaScript pada run final.

Screenshot homepage tersedia untuk ketiga preset pada 1440 dan 390; screenshot editor pada 1440. Peninjauan visual langsung mencakup Classic desktop, Blue desktop, Forest mobile, dan editor desktop. Artefak lokal: `storage/app/public-theme-qa/results.json` serta PNG pada direktori yang sama. Font Google dan thumbnail eksternal sengaja diblokir untuk pengujian offline. Tidak ada dependency browser yang ditambahkan ke aplikasi.

Bundle terbaru: `public-Ck3tHkP5.css`, `public-CHAGiyuT.js`, `admin-ComW-fuB.css`, `admin-CDKbe_td.js`, `article-editor-CTiSsTr5.js`, `article-form-B82X9Wtj.js`, serta `prism-markup-templating-Ct1xsyfA.js`. Bundle lama yang digantikan dihapus oleh Vite; semua referensi baru berada pada manifest.

Untuk menjalankan ulang browser QA, gunakan SQLite bernama `public-theme-qa.sqlite` pada `storage/app`, `DB_CONNECTION=sqlite`, `SESSION_DRIVER=file`, `CACHE_STORE=array`, dan jalankan `php tests/browser/seed-public-theme.php`. Jalankan server lokal dengan environment yang sama, `APP_ENV=production` (memakai build, mengabaikan public/hot existing), `APP_URL=http://127.0.0.1:8767`, dan `php artisan serve --host=127.0.0.1 --port=8767 --no-reload`. Set `PLAYWRIGHT_MODULE` ke instalasi existing `playwright-core/index.mjs`, serta `THEME_QA_CHROMIUM` ke Chromium existing, kemudian `node tests/browser/public-theme.mjs`. Tidak perlu mengubah `.env`.

Font Google dan thumbnail YouTube diblokir pada QA offline. Screenshot memakai font fallback lokal; rendering font eksternal, konten gambar production, MySQL/cPanel deployment, dan browser selain Chromium belum diverifikasi langsung.
