# Red White: audit save dan snapshot Livewire

Status: **LOCAL VERIFIED; akar masalah production belum terbukti, bukan CODE FIXED untuk 405 production**. Tidak ada deploy, login/save production, perubahan APP_KEY, atau perubahan warna preset pada audit ini.

## Perbandingan terkontrol

Repository memakai Laravel 13.9.0 / Livewire 4.3.0. Red White memiliki nama preset unik, 26 token lengkap, HEX valid, dan seluruh pasangan kontras memenuhi target existing. Keempat preset memakai registry `PublicTheme::presets()`, state Alpine `colors`, form `wire:submit="save"`, `PublicThemeEditor::save()`, dan transaksi `PublicTheme::save()` yang sama. Tidak ada lifecycle, listener, redirect, atau handler khusus Red White. Form tema berada di luar form Settings konvensional; tidak ada form bersarang.

Browser Chromium melalui Apache native Windows, memakai database SQLite QA terpisah:

| Preset | Request saat memilih | Request per klik Save | Komponen / action | Respons | Memo asal sebelum/sesudah |
| --- | --- | --- | --- | --- | --- |
| Besofton Classic | 0 | 1 POST | public-theme-editor / save | 200 JSON | admin/settings / GET |
| Forest Green | 0 | 1 POST | public-theme-editor / save | 200 JSON | admin/settings / GET |
| Modern Blue | 0 | 1 POST | public-theme-editor / save | 200 JSON | admin/settings / GET |
| Red White | 0 | 1 POST | public-theme-editor / save | 200 JSON | admin/settings / GET |

`updates` berisi hanya token yang berbeda dari state sebelumnya. Contohnya, Modern Blue ke Red White mengirim 21 key warna, sedangkan memilih Red White kembali setelah reload mengirim `updates: {}`. Ini normal: warna yang tidak berubah tetap berada pada state/snapshot. Setiap Save tetap memvalidasi dan menyimpan seluruh 26 token.

Urutan Classic → Forest Green → Modern Blue → Red White → Classic → Red White → Red White diuji, dengan reload dan pemeriksaan semua 26 input setelah setiap Save. Red White juga disimpan setelah tiga siklus wire:navigate/Back/Forward. Total 14 Save berhasil; satu komponen dan satu call save per request, tanpa redirect effects, GET update, atau navigasi halaman ke endpoint. Preview dan warna homepage sesuai. Console/page error kosong, listener beforeunload tetap 2 dan livewire:navigate tetap 4. Jumlah ini mencakup listener framework existing.

Browser yang diuji adalah Chromium lokal, bukan Firefox production. Artefak `storage/app/livewire-transport-qa/chromium-results.json` menyimpan perbandingan nama preset, action, key updates, memo name/path/method, status, dan hitungan listener; tidak menyimpan token, checksum, id komponen, atau snapshot lengkap.

## Mengapa POST dapat menghasilkan exception GET

Livewire menjalankan ulang persistent middleware untuk route halaman asal. Pada vendor lokal, `PersistentMiddleware::makeFakeRequest()` membuat request internal memakai `memo.path` dan `memo.method`. GET pada memo halaman Settings memang benar: halaman asal dibuka menggunakan GET, sedangkan transport update memakai POST.

Jika snapshot terverifikasi benar-benar membawa path endpoint update dan method GET, pencocokan route internal dapat menghasilkan 405 walaupun browser mengirim POST. **Kondisi itu tidak ditemukan pada pengujian normal dan belum dibuktikan pada production.** Jangan mengubah memo secara manual, mematikan persistent middleware, atau menambahkan route GET.

Bukti pengguna adalah dua POST dengan respons 200 lalu 405. Bukti itu belum menunjukkan komponen/action pada masing-masing request, memo respons pertama, atau frame yang melempar 405. Perbedaan hanya pada production tidak cukup untuk menyalahkan warna, proxy, WAF, atau vendor tertentu. Perbaikan trailing-slash dari audit sebelumnya bukan bukti akar masalah Red White ini.

## Perubahan pada audit ini

- `tests/Feature/LivewireTransportTest.php`: tambah rangkaian HTTP sungguhan sync-property tanpa call lalu Save memakai snapshot respons sebelumnya, untuk tujuh pergantian preset. Kedua respons harus tetap memiliki id/name/path/method asal yang sama, tanpa error/redirect; sync tidak menulis database, Save menyimpan 26 token dan mengirim satu event sukses. Registry preset tetap identik.
- `tests/browser/livewire-transport.mjs`: perluas perbandingan keempat preset, payload bundle/action/key updates, memo sebelum/sesudah, 26 warna setelah reload, Red White berulang, dan setelah history/navigation. Tidak mengubah snapshot.
- `app/Http/Middleware/LogLivewireTransportFailures.php`: pada kegagalan 405/redirect saja, tambahkan `verified_origins`. Pengamat berjalan setelah checksum lolos, sebelum persistent routing, dan dilepas pada `finally`. Maksimal sepuluh entri; komponen hanya kategori editor/other, origin hanya settings/update/other, method hanya GET/POST/other. Tidak membaca atau menulis ulang snapshot untuk memperbaiki request.
- Test diagnostik menyuntikkan exception routing setelah verifikasi untuk membuktikan bahwa incoming POST dan rejected GET dapat dibedakan, tanpa mengubah/re-sign snapshot. Ini fault injection untuk menguji log, bukan reproduksi akar masalah production. Exception tetap gagal 405, tidak menjadi sukses palsu; request valid berikutnya tetap dapat menyimpan.
- Dokumentasi audit dan transport diperbarui. Tidak mengubah routes, warna, service Save, CSS/JS produk, auth, CSRF, proxy, database schema, atau `.cpanel.yml`.

## Verifikasi dan deployment

Hasil perintah lengkap dicatat pada [audit transport](livewire-transport.md#verifikasi-lokal). `optimize:clear` berhasil; seluruh **248 test PHP lulus, 2.550 assertion**, termasuk **14 test HTTP transport, 389 assertion**. Browser lokal: **14 Save berhasil**. JavaScript existing: **13 test lulus**. Composer valid, Pint lulus, build Vite berhasil dengan 165 modul.

Update source dapat memakai Git Version Control cPanel existing; `.cpanel.yml` sudah menyegarkan cache dengan urutan yang benar. Perubahan ini tidak memerlukan dependency baru, migration baru, Terminal, atau penggantian APP_KEY. Tidak dilakukan push/deploy otomatis. Pertahankan `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL=https://blog.besofton.id` di `.env` hosting, di luar Git.

Jika production masih gagal, File Manager dapat membaca baris `Livewire transport failure.` di `storage/logs/laravel.log`:

- `entry_method=POST`, `rejected_method=GET`, `verified_origins.origin=update`, method GET: snapshot terverifikasi menunjuk endpoint update; cocokkan frame PersistentMiddleware dan memo respons 200 sebelumnya untuk mencari pembentuk state tersebut.
- `entry_method=POST`, origin settings/GET: memo yang diterima masih benar; frame exception diperlukan untuk menentukan kegagalan routing internal berikutnya.
- `entry_method=GET` atau verified_origins kosong: belum membuktikan masalah snapshot; periksa frame serta apakah request mencapai/verifikasi Livewire. Kosong juga normal jika routing/CSRF gagal sebelum hydration.

Data tambahan yang dibutuhkan dari dua request production: hanya memo name/path/method, calls method, nama key updates, serta 3–5 nama file/method setelah AbstractRouteCollection.php. Jangan membagikan HAR mentah, payload, cookie, token, checksum, atau kredensial. Sampai bukti tersebut tersedia dan Save diuji pada hosting, status **PRODUCTION NOT VERIFIED**.
