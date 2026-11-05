# Perbaikan HTTP 500 konfigurasi public theme

Tanggal audit: 6 November 2026.

## Penyebab dan bukti

`PublicTheme::fields()` sebelumnya langsung memanggil `array_values(config('public_theme.groups'))`. Ketika key itu tidak ada, Laravel mengembalikan `null` dan PHP melempar TypeError. Tidak ada pemeriksaan struktur group/field/shade sebelum array diakses.

Kedua file konfigurasi, service, seeder, dan `.cpanel.yml` sudah tercatat oleh `git ls-files`; file tersebut bukan file yang diabaikan Git. Urutan deploy sebelumnya menjalankan `migrate --force --seed` sebelum `optimize:clear`. Karena Laravel memakai seluruh konfigurasi dari cache yang ada dan tidak membaca file config baru ketika cache aktif, cache dari versi sebelum fitur tema dapat menghilangkan `public_theme.groups` pada saat seeder dijalankan. Skenario ini direproduksi dengan boot aplikasi dari file cache nyata yang tidak memiliki konfigurasi tema. Cache/server production tidak diakses, jadi keadaan source dan cache aktual di hosting belum dikonfirmasi langsung.

## Perubahan

- `app/Support/PublicThemeDefaults.php`: satu sumber definisi Classic lengkap (26 token, 89 shade), tersedia tanpa mengandalkan config repository/cache. Nilai, label, dan bantuan dipindahkan dari konfigurasi existing tanpa mengganti palet.
- `config/public_theme.php` dan `config/public_theme_shades.php`: merujuk array konstanta tersebut. Hasil konfigurasi tetap array biasa dan dapat diserialisasi oleh `config:cache`.
- `app/Support/PublicTheme.php`: validasi group, field, keunikan/kelengkapan token, tuple metadata, default HEX, dan nama/parent/HEX shade. Struktur null, kosong, scalar, parsial, atau malformed diganti fallback lengkap di config repository request saat itu saja. File config/cache dan database tidak ditulis oleh fallback.
- Kegagalan dicatat sebagai `Log::error` dengan key konfigurasi, alasan, status cache, dan tindakan perbaikan. Payload warna/CSS yang tidak valid tidak dimasukkan ke pesan log. Tidak ada fallback diam-diam. Config juga diperiksa ketika resolved theme sudah berada di cache aplikasi.
- Resolver tetap melapisi definisi fallback dengan warna custom valid dari tabel Settings. Homepage tetap mempunyai semua variabel; fallback bukan reset tema database.
- `app/Livewire/PublicThemeEditor.php` dan view-nya memakai groups/shades yang sudah divalidasi; editor tidak membaca raw config yang berpotensi rusak.
- `PublicThemeSettingsSeeder` tetap memakai `firstOrCreate`, termasuk saat config belum tersedia. Hanya key yang belum ada yang ditambahkan. Test membandingkan nilai serta timestamp record custom sebelum/sesudah dua kali seed.
- `DatabaseSeeder` sudah memanggil `PublicThemeSettingsSeeder` dan tidak memanggil `DemoSeeder`; integrasi itu diuji tanpa mengubah business logic seeder existing.
- `.cpanel.yml`: `set -e`, `config:clear`, `migrate --force --seed`, `optimize:clear`, `config:cache`, `view:cache`. Path aplikasi dan PHP hosting tetap sama. Urutan cache sesudah migration dipertahankan agar konfigurasi yang dicache berasal dari source baru.

## Deployment cPanel

Task tetap berupa daftar perintah Bash yang didukung cPanel Git Version Control. Workflow **Update from Remote > Deploy HEAD Commit** tidak membutuhkan Terminal; lihat [panduan lokal](deployment-cpanel.md) dan [dokumentasi resmi cPanel](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/).

Semua file perbaikan, termasuk class default baru, harus disertakan dalam commit deployment. Repository hosting harus bersih, source aplikasi harus sesuai commit pada path APPPATH existing, dan PHP/path/writable cache directory harus tersedia di hosting. Tidak menambah proses copy source karena konfigurasi existing tidak memilikinya. Cache konfigurasi lokal tidak boleh disalin ke hosting.

Tidak ada deployment production yang dijalankan, reset database, atau perubahan nilai tema database aplikasi. Pengujian seed hanya berlangsung pada SQLite in-memory milik tes.

## Verifikasi

Regression suite baru mencakup config normal, null/kosong/malformed, semua token wajib, malformed shade/CSS, halaman publik/editor, resolved cache yang sudah terisi, preservation/idempotence seeder, DatabaseSeeder tanpa DemoSeeder, boot konfigurasi cached nyata, dan urutan task cPanel.

| Pemeriksaan | Hasil |
| --- | --- |
| Regression konfigurasi tema | 37 test lulus, 256 assertion |
| `php artisan test --compact --no-ansi` | 231 test lulus, 2.052 assertion |
| `php artisan config:cache --no-ansi` | Berhasil pada konfigurasi aplikasi lokal |
| `php artisan migrate:status --no-ansi` | Berhasil membaca status: 15 Ran, 1 Pending |
| `npm.cmd run build` | Berhasil, 165 modul; bundle/manifest tidak berubah |
| `php vendor/bin/pint --test --quiet` | Lulus |
| `git diff --check` | Bersih |
| YAML cPanel | Berhasil diparse, urutan task dan pengaktifan set -e terverifikasi oleh regression test |

Tes cached configuration menjalankan `config:cache` dalam proses PHP terpisah dengan path cache sementara, lalu mem-boot aplikasi dari file tersebut dalam kondisi normal, konfigurasi tema hilang, dan struktur malformed. Fallback tidak memodifikasi isi file cache. File sementara dihapus setelah tes.

Saat verifikasi perintah lokal, migration existing `2026_10_06_000001_add_focus_keyphrase_to_posts` masih Pending. Tidak dibuat atau dijalankan migration baru, dan pending migration tersebut tidak dijalankan. Cache config lokal dibersihkan kembali setelah pemeriksaan untuk memulihkan kondisi awal (sebelumnya tidak ada `bootstrap/cache/config.php`).

Keamanan urutan task dan validitas YAML sudah diperiksa di repository. Eksekusi aktual cPanel, kesesuaian checkout terhadap APPPATH di hosting, dan state cache production belum diuji karena deployment production tidak dijalankan.
