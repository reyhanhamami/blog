# Livewire 4 — audit HTTP 405 dan perbaikan transport

Status production: **NOT VERIFIED**. Tidak ada deploy production, perubahan APP_KEY, atau penyimpanan tema pada production selama audit ini.

Audit lanjutan untuk laporan bahwa hanya Red White gagal tersedia di [perbandingan preset dan snapshot](red-white-livewire-audit.md). Dua POST production (200 lalu 405) belum dapat direproduksi; jangan menganggap trailing-slash sebagai akar masalah kasus tersebut.

## Temuan yang benar-benar dibuktikan

Audit repository memakai Laravel 13.9.0 dan Livewire 4.3.0 dari composer.lock. Tidak ditemukan override `setUpdateRoute`, hardcoded hash endpoint, route GET untuk update, forceScheme/forceRootUrl, wildcard trusted proxy, fetch interceptor, atau inisialisasi Livewire/Alpine ganda. Entry point `public/index.php` tidak mengganti REQUEST_METHOD. Root `.htaccess` tidak ada dalam repository; file aktif yang diaudit adalah `public/.htaccess`.

Livewire mendaftarkan satu route update POST beserta web/CSRF middleware dan header guard. URL update berasal dari resolver paket, bukan string hardcoded. `@livewireScripts` dipasang satu kali pada layout admin; asset auto-injection dimatikan oleh config existing. Form tema menggunakan `wire:submit="save"`, tombol submit benar, dan JavaScript editor tidak mengirim GET atau menavigasi ke endpoint update.

Pemeriksaan read-only production memperoleh hasil berikut. Cookie, token, dan snapshot hanya digunakan dalam memori dan tidak dicatat. `$refresh` dilakukan pada komponen newsletter publik, bukan aksi menyimpan tema/berlangganan.

| Pemeriksaan | Hasil |
| --- | --- |
| GET homepage HTTPS | 200; satu script Livewire, update URI HTTPS pada host yang benar |
| POST canonical tanpa CSRF | 419; tidak ada Location |
| POST canonical dengan CSRF/snapshot publik valid, `$refresh` | 200; tidak ada Location |
| POST update dengan slash tambahan | 301 menuju endpoint tanpa slash |
| Fetch mengikuti 301 tersebut | Respons akhir 405 pada endpoint canonical |
| Cookie sesi pada respons publik HTTPS | Secure, HttpOnly, SameSite=Lax |

Rantai trailing-slash tersebut mereproduksi satu jalur kegagalan POST menjadi GET. Aturan `R=301` di repository cocok dengan perilaku itu. Namun header `Server: nginx` tidak cukup untuk memastikan apakah redirect production berasal dari Apache di belakang nginx atau aturan proxy di depannya.

Pengguna kemudian mengonfirmasi bahwa request Simpan Tema yang menerima 405 di Firefox Network masih tercatat **POST**. Karena itu, redirect trailing slash **belum terbukti sebagai penyebab request Simpan Tema tersebut**. Pesan exception yang menyebut GET juga dapat berasal dari routing request internal Laravel/Livewire, atau request yang diganti sebelum masuk PHP. Root cause spesifik request admin masih membutuhkan nama frame routing/metadata server yang bersangkutan. Jangan menyamakan temuan pada request publik berslash dengan pembuktian akar masalah admin.

Percobaan lanjutan dari lingkungan QA untuk membandingkan penanda asset production mengalami connection timeout. Versi vendor yang benar-benar terpasang di hosting belum diverifikasi; versi lokal dari lockfile tidak boleh dianggap otomatis sama dengan hosting.

## Perubahan aplikasi

### public/.htaccess

Canonical trailing-slash redirect sekarang hanya berjalan untuk GET/HEAD. POST dan action lain langsung diteruskan ke front controller Laravel; Laravel menerima path update berslash tanpa mengubah method. Redirect 301 untuk halaman GET tetap seperti sebelumnya. Authorization header, X-XSRF header, Options, dan front-controller rule tidak dihapus atau dilonggarkan.

Perubahan ini memperbaiki jalur 301 yang dapat direproduksi, bukan workaround route GET. Endpoint GET update tetap ditolak dengan 405.

### bootstrap/app.php

Ditemukan masalah autentikasi terpisah pada pengujian: default fetch Livewire mengirim `Accept: */*`, dan sesi admin yang habis menghasilkan 302 login. Exception renderer kini mengenali header X-Livewire pada route update dan membalas error dalam JSON; sesi habis menjadi 401 tanpa Location. Respons non-Livewire tetap mengikuti `expectsJson()` existing. Tidak melewati authentication, permission, CSRF, atau validasi snapshot.

Tidak ada override URL/route, trust proxy baru, force HTTPS baru, perubahan session driver/domain, APP_URL, APP_KEY, config Livewire, atau algoritma tema.

### app/Http/Middleware/LogLivewireTransportFailures.php

Middleware diagnostik terdaftar melalui bootstrap dan hanya mencatat request X-Livewire update yang menghasilkan 405 atau redirect. Respons dan exception tidak diubah/dipulihkan secara otomatis.

Metadata log `Livewire transport failure.`:

- `entry_method`: method nyata saat masuk middleware;
- `method`, `server_method`: method Laravel dan server setelah pemrosesan;
- `rejected_method`: method yang disebut exception 405, jika tersedia;
- `routing_frames`: maksimal lima nama file/class/function routing, tanpa argumen atau path penuh;
- `verified_origins`: maksimal sepuluh kategori komponen/origin/method dari snapshot setelah checksum lolos; origin hanya settings/update/other, tanpa snapshot mentah. Listener dilepas pada akhir request;
- `internal_redirect_status`: REDIRECT_STATUS Apache bila ada;
- path update, status, scheme, ringkasan forwarded proto, apakah proxy dipercaya, dan boolean Location.

Tidak mencatat cookie, CSRF token, kredensial, payload, snapshot, query string, argumen stack, atau exception object.

Interpretasi untuk request admin yang masih gagal:

| Bukti | Langkah berikutnya |
| --- | --- |
| Firefox POST, `entry_method=POST`, `rejected_method=GET`, frame PersistentMiddleware/router | Periksa routing internal Livewire, memo path/method, dan kesesuaian vendor dengan lockfile; bukan bukti bahwa browser mengirim GET |
| Firefox POST, `entry_method=GET` | Bandingkan access/error log nginx/Apache dan ErrorDocument/WAF sebelum Laravel |
| `internal_redirect_status` 403/406 pada kasus tersebut | Cocokkan dengan audit rule ModSecurity hosting pada waktu yang sama; jangan menonaktifkan WAF/CSRF secara menyeluruh |
| POST 301/302/303 di Network | Periksa Location dan aturan redirect di layer yang benar; setelah patch ini, Apache action URL tidak terkena canonical 301 milik aplikasi |
| Update 401 | Sesi habis/guest; login ulang, tidak ada penyimpanan tema |
| Update 419 | CSRF/session tidak cocok; jangan membuka pengecualian CSRF untuk update |

## Settings dan kompatibilitas

Save tetap memakai Livewire POST, validasi backend, transaksi Settings, invalidasi cache, dan toast existing. Seluruh empat preset tetap dapat disimpan. Seeder tetap idempotent dan mempertahankan custom theme. Tidak ada perubahan branding, homepage, atau desain CMS.

Branding/Homepage di `/admin/settings` menggunakan form PATCH konvensional existing, bukan komponen Livewire. Browser QA menyimpan form itu tanpa perubahan data fixture dan memastikan redirect PRG 302 menuju Settings, bukan endpoint Livewire. Komponen newsletter publik diuji dengan `$refresh` tanpa penyimpanan.

`.cpanel.yml` sudah menjalankan `config:clear` sebelum migrate/seed, lalu optimize:clear (termasuk route/view cache), config:cache, dan view:cache. Urutan serta path PHP hosting tidak perlu diubah pada perbaikan ini. Tidak ada perintah key:generate, migrate:fresh, atau db:wipe dalam task deployment.

## Verifikasi lokal

Browser Chromium menggunakan Apache 2.4.58 native Windows dan PHP 8.3 dengan `.htaccess` repository yang sebenarnya. Konfigurasi server QA dibuat pada storage, mendengarkan localhost saja, dan tidak mengubah konfigurasi/service WAMP sistem. Database QA tetap SQLite terpisah yang dijaga oleh fixture.

Hasil browser:

- Empat belas klik Save sukses: keempat preset, Red White berulang, serta warna custom/riwayat navigasi. Setiap klik mengirim tepat satu request POST dengan satu call save; tidak ada redirect atau navigasi halaman ke update URI. Memilih preset tidak mengirim request otomatis; memo tetap admin/settings/GET sebelum dan setelah Save, dan semua 26 input sesuai setelah reload.
- Reload Settings dan homepage membaca warna terbaru.
- Tiga siklus wire:navigate/Back/Forward tetap dapat menyimpan; jumlah listener stabil (beforeunload 2, livewire:navigate 4).
- Satu script Livewire, URI/hash tetap sama selama pengujian.
- Request canonical dan berslash berhasil 200 tanpa Location melalui Apache.
- GET update tetap 405. GET halaman berslash tetap 301.
- Dengan header fetch Livewire sebenarnya (`Accept: */*`): CSRF salah 419, guest/sesi habis 401, editor tanpa settings permission 403; semuanya tanpa redirect.
- Tidak ada page error atau console error pada run Chromium final.

Firefox automation tidak berhasil membuat halaman (`Cannot read properties of undefined (reading '_page')`) sebelum aplikasi dimuat, pada dua percobaan menggunakan instalasi existing. Tidak ada klaim Firefox PASS. Ini keterbatasan runner, bukan bukti perilaku aplikasi pada Firefox pengguna.

Artefak aman lokal: `storage/app/livewire-transport-qa/chromium-results.json` dan `routes.txt`. Request payload, cookie, dan token tidak disimpan dalam artefak.

Hasil verifikasi akhir:

| Pemeriksaan | Hasil |
| --- | --- |
| php artisan optimize:clear | Berhasil |
| php artisan route:list | 191 route termasuk vendor; tidak ada route GET update baru |
| php artisan route:list --path=livewire -v | 9 route Livewire; satu route update POST dengan web/header guard |
| php artisan test --compact --no-ansi | 248 test lulus, 2.550 assertion |
| Regression HTTP transport | 14 test lulus, 389 assertion |
| composer validate | composer.json valid |
| php vendor/bin/pint --test --quiet | Lulus |
| npm.cmd run build | Berhasil, 165 modul; bundle/manifest tidak berubah |
| node --test tests/js/*.test.js | 13 test JavaScript lulus |
| Chromium + Apache native | PASS untuk alur lokal yang dijelaskan di atas |
| Firefox automation | NOT VERIFIED: runner gagal membuat halaman |
| Production Save Theme authenticated | NOT VERIFIED: tidak dilakukan login/save/deploy production |
| git diff --check | Bersih |

Test HTTP mencakup penolakan GET, URI script yang sesuai route, empat preset, validasi HEX tanpa partial write, hasil cache terbaru, seeder yang mempertahankan warna, APP_KEY invariant, sesi guest/expired dengan Accept */*, permission, CSRF tanpa bypass unit test, HTTPS serta spoofed proxy header, dan log metadata POST yang ditolak sebagai GET internal. Snapshot dan token untuk test hanya fixture lokal/in-memory.

File perubahan aplikasi: public/.htaccess, bootstrap/app.php, app/Http/Middleware/LogLivewireTransportFailures.php. File dukungan: tests/Feature/LivewireTransportTest.php, tests/browser/livewire-transport.mjs, tests/browser/prepare-livewire-apache.php, tests/browser/seed-livewire-transport.php, docs/livewire-transport.md, docs/deployment-cpanel.md. Routes, config Livewire/session/proxy, APP_KEY, komponen/theme service, dan .cpanel.yml tidak diubah.

## Production melalui cPanel tanpa SSH

1. Review/commit semua file perbaikan, termasuk middleware baru, lalu gunakan Git Version Control **Update from Remote > Deploy HEAD Commit** ketika siap. Deployment tidak dijalankan oleh agen.
2. Pastikan working tree/source APPPATH sesuai commit, dan `public/.htaccess` benar-benar sampai pada document root yang dipakai subdomain. Bila canonical POST masih 301 setelah patch, minta hosting memeriksa aturan nginx/Apache di luar repository; jangan menambah route GET update.
3. Di File Manager, periksa `.env` hosting: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://blog.besofton.id`. Pertahankan APP_KEY yang sudah ada. Cookie sesi HTTPS harus tetap secure; tidak perlu mengganti driver/domain tanpa bukti.
4. Cache disegarkan oleh task `.cpanel.yml`, bukan Terminal. Jangan mengunggah config/route cache lokal. Bila vendor tidak sesuai lockfile, siapkan vendor dari `composer install --no-dev --optimize-autoloader` di development/staging dan terapkan lewat jalur file deployment hosting yang sudah tersedia; jangan mengubah APP_KEY untuk mengganti hash.
5. Buka Settings baru/full reload, DevTools Network **Preserve log**, lalu pilih Red White, ubah Primary, Simpan, reload, dan periksa homepage. Ulangi setelah pindah halaman/Back/Forward. Cari POST canonical, status 200, tanpa Location dan tanpa GET update. Guest/session expired harus gagal tanpa menyimpan warna.
6. Jika request Firefox POST tetap 405, baca `storage/logs/laravel.log` melalui File Manager untuk baris diagnostik tersebut. Bagikan hanya method/status/scheme, nama file/method routing, dan waktu kejadian. Bandingkan log hosting bila PHP sudah menerima GET. Jangan membagikan HAR mentah, snapshot, cookie, token, atau kredensial.

Tidak ada endpoint maintenance web atau bypass keamanan yang ditambahkan. Status produksi tetap **NOT VERIFIED** sampai Save Theme authenticated benar-benar diuji pada hosting setelah perubahan diterapkan.
