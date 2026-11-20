# Article save 405 & ChatGPT clipboard

Audit/perbaikan 20 November 2026. Tidak ada deployment atau penyimpanan artikel production. Tidak ada migration baru, reset database, penghapusan artikel/revisi, perubahan APP_KEY, pengecualian CSRF, atau perubahan aturan WAF.

## Temuan berdasarkan bukti

| Temuan | Status dan bukti |
| --- | --- |
| Route dan redirect sukses | PASS; create POST dan update POST dengan `_method=PATCH` menuju controller existing. Sebelum patch, keduanya sudah redirect ke `admin.posts.edit`. Tidak ditemukan JS yang mengubah metode save menjadi GET. |
| Redirect validasi | FAIL sebelum patch, PASS sesudah; validasi default memakai URL sebelumnya. Regression test dengan Referer action menghasilkan Location `/admin/posts/{id}`, lalu GET tersebut 405. Tanpa Referer/riwayat valid, fallback juga bisa salah. Redirect validasi sekarang ditetapkan ke create/edit, termasuk validasi slug dan referensi manual. Ini jalur aplikasi yang direproduksi; belum terbukti Referer production memang demikian. |
| Draf dihapus sebelum hasil save | FAIL sebelum patch, PASS sesudah; listener submit sebelumnya langsung menghapus localStorage dan menandai editor bersih. Draf baru dibersihkan sesudah server berhasil menyimpan dan mengembalikan respons JSON yang valid. |
| Penolakan server meninggalkan editor | FAIL sebelum patch, PASS sesudah; submit native membawa browser ke halaman respons error. Progressive enhancement baru mempertahankan DOM/form ketika request gagal, termasuk 403/405/413/419/500 atau respons 200 HTML yang bukan hasil save. |
| Chrome paste seluruh isi | FAIL pada reproduksi awal; replace artikel yang diawali H1 melalui execCommand dapat meninggalkan H1 pembungkus paragraf, berbeda sesudah reload. Penggantian seluruh isi memakai fragment DOM yang disanitasi; selection parsial tetap memakai perilaku editor existing dan tidak dianggap seluruh dokumen jika ada gambar/tabel/HR di luar selection. |
| Struktur tabel dan daftar | FAIL sebelum patch, PASS sesudah; sanitizer existing membuang colspan/rowspan/scope serta ol start/li value. Kedua sanitizer existing sekarang mempertahankan atribut semantik yang dibatasi, tetap membuang style/handler dan atribut lain. |
| Apache 403 → GET → 405 | PASS untuk reproduksi **lokal yang disengaja**, bukan bukti WAF production. Apache menolak POST berdasarkan header QA, lalu ErrorDocument internal mengarah ke action. PHP menerima GET; Laravel menjawab 405. Log: `method=GET`, `server_method=GET`, `internal_redirect_status=403`, `parent_redirect_status=403`. Editor baru tetap terbuka dengan konten utuh. |
| WAF/ModSecurity/Imunify360 production | NOT VERIFIED; tidak tersedia access/error/audit log hosting atau authenticated failing request. Header Server nginx tidak membuktikan atau meniadakan WAF/Apache di belakangnya. |

Dasar framework: [Laravel validation](https://laravel.com/framework/docs/validation/latest) menjelaskan redirect validasi biasa ke halaman sebelumnya dan JSON 422 untuk request JSON. Source vendor yang dipasang (`Handler::invalid`) menggunakan `exception->redirectTo ?? url()->previous()`. [Apache REDIRECT variables](https://httpd.apache.org/docs/current/env.html) menjelaskan asal `REDIRECT_STATUS`; nilai 200 dapat berasal dari rewrite normal, sehingga **bukan bukti WAF**. [ErrorDocument](https://httpd.apache.org/docs/current/mod/core.html#errordocument) perlu diperiksa bersama konfigurasi hosting.

## Alur penyimpanan

- Form HTML tetap `method=post`, action store/update existing, `@csrf` dan edit `@method('PATCH')`. Tidak ada GET action baru atau penggantian arsitektur CMS/Livewire.
- Saat JS siap, submit menyinkronkan editor dan OG mode, menulis cadangan body tab, lalu mengirim FormData POST same-origin dengan Accept JSON. Method spoofing tetap di backend. Tidak ada encoding/base64 untuk menghindari WAF.
- Hanya satu save pending per form. Form inert dan tombol submit disabled selama request; toolbar existing tetap type button. Generic guard app.js melewati event yang sudah ditangani; tidak ada submit ganda.
- Server melakukan authorization, validasi dan PostWriter/transaksi/revision existing. Setelah commit, respons JSON hanya berisi URL edit. Client memeriksa origin dan pola URL edit sebelum navigasi normal. Success flash baru dibuat setelah save selesai.
- Redirect jaringan yang tidak diharapkan ditolak (`redirect: error`), sehingga browser tidak mengikuti 302/303 hosting menuju GET action. Respons 422 menampilkan pesan field dengan textContent; respons lainnya mendapat pesan aman tanpa memasukkan HTML exception dari server ke DOM.
- Error/koneksi putus/timeout 45 detik tidak memicu retry otomatis. Server mungkin sudah commit ketika respons hilang: periksa daftar artikel di tab lain sebelum mengulangi. Slug unique backend tetap aktif. Ini bukan janji exactly-once lintas retry jaringan.
- Tanpa JS, form native tetap tersedia; sukses dan validasi aplikasi kembali ke editor yang benar. Perlindungan mempertahankan DOM saat blokir hosting membutuhkan JS. Kebijakan auth/session/CSRF existing tetap berlaku.

## Clipboard, sanitasi dan draft

Markdown **tidak dikonversi otomatis**: heading `##`, tabel pipe dan `**bold**` dalam text/plain tetap teks. Paragraf/newline ditampilkan aman, bukan HTML executable. Rich clipboard HTML disisipkan setelah allowlist existing membersihkan markup; server tetap mengulang sanitasi melalui `HtmlSanitizer` sebelum menyimpan. Tidak ada sanitizer/library alternatif.

H1–H6, paragraf, strong/em, ordered/unordered lists, tabel thead/tbody/tfoot/tr/th/td, serta URL HTTPS aman dipertahankan. H1 isi tidak otomatis diubah menjadi H2; analyzer existing tetap memberi panduan karena judul halaman sudah H1. Elemen tak dikenal dibuka pembungkusnya; script/style/iframe/form/SVG dan elemen aktif lain dibuang, begitu pula komentar clipboard, event handler dan CSS sembarang. `colspan/rowspan` hanya angka positif 1–999, `scope` allowlist, `start/value` signed integer maksimal enam digit. Alt/dimensi gambar dan class editor aman tetap mengikuti aturan existing.

Editor lama memakai localStorage body tanpa expiry dan key path saja. Cadangan baru memakai **sessionStorage**, key user+path, umur logis **24 jam**, maksimal 1.000.000 karakter per body. Hanya body tersanitasi, tanpa cookie/session/CSRF/title/metadata form. Tab ditutup mengakhiri penyimpanan sessionStorage; nilai kedaluwarsa dibuang saat dibaca. Draf tidak dikirim otomatis ke server. Storage diblokir/penuh tidak menghentikan editor; status menyarankan salin manual. Pemulihan memerlukan pilihan eksplisit dan disanitasi ulang.

Draf localStorage versi lama tidak dibaca lintas pengguna atau dihapus otomatis oleh patch ini. Jika diperlukan, pemilik browser dapat menyalin draf lama secara manual sebelum membersihkan penyimpanan situs. Cadangan tab bukan pengganti save CMS, tidak menjamin pemulihan setelah tab ditutup/browser crash, dan bukan sarana penyimpanan informasi rahasia. Konten yang belum pernah dimuat aplikasi tidak dapat dipulihkan dari request yang diblokir sebelum Laravel; perlindungan di sini berada pada editor/browser yang masih terbuka.

## Diagnostik hosting tanpa isi artikel

Middleware `LogArticleSaveFailures` hanya untuk store/update artikel dan GET action yang ditolak. Log `Article save transport failure.` memuat effective method, real server method, status, REDIRECT_STATUS/parent, Content-Length, PHP post_max_size, dan boolean Location. Tidak merekam payload, judul, body, URL/query, cookie, token, atau exception object. Tidak mengubah status atau memperbaiki internal redirect secara diam-diam. `PrivateRobots` tetap hanya menambahkan header noindex.

Untuk kasus production yang berulang, simpan waktu kejadian **WIB dan UTC**, method, status, Content-Type, dan urutan Location dari Network. Jangan membagikan HAR mentah, Cookie, CSRF, kredensial, atau seluruh artikel privat.

1. Cocokkan waktu dengan Laravel log. Jika PHP menerima GET dengan REDIRECT_STATUS 403/406, cocokkan lagi dengan access/error log nginx/Apache dan ModSecurity/Imunify audit log. Absennya log Laravel dapat berarti request ditolak sebelum middleware, bukan bukti save sukses.
2. Minta provider menunjukkan **rule ID, unique request ID, matched variable, status asli**, dan konfigurasi ErrorDocument/parent `.htaccess` yang berlaku. Root `.htaccess` hosting di luar repository belum terverifikasi. Perbaiki false positive khusus rule/endpoint melalui provider, tanpa menonaktifkan WAF/XSS global atau mengizinkan User-Agent secara membabi buta.
3. Periksa `LimitRequestBody`, `SecRequestBodyLimit`, `SecRequestBodyNoFilesLimit`, PHP `post_max_size`, `max_input_vars`, memory/time limits dan batas proxy. File upload Media Library terpisah dari body artikel. Jangan menaikkan semua limit tanpa bukti. PHP menolak body terlalu besar sebelum validasi dapat menyebabkan input kosong, sehingga `_method` juga dapat hilang.
4. Jika Location 301/302/303 muncul, periksa layer yang mengeluarkannya. `.htaccess` repository sudah membatasi canonical trailing-slash redirect pada GET/HEAD; tidak ada ErrorDocument atau aturan security bypass baru di source ini.
5. 419 berarti token fallback CSRF/session mismatch; 401 berarti login tidak berlaku; 422 berarti validasi field. Laravel 13 `PreventRequestForgery` menerima origin browser `Sec-Fetch-Site: same-origin` yang valid atau token yang cocok; token salah pada request same-origin saja bukan bukti proteksi dimatikan. Test token fallback memakai APIRequestContext tanpa hint origin browser; UI 419 diuji terpisah dengan respons penolakan. Login di tab lain dapat memperbarui session token sehingga klien tanpa hint origin perlu salin/recovery lalu reload. Jangan mematikan proteksi request forgery untuk mengatasinya.

## Pengujian

| Pemeriksaan | Hasil |
| --- | --- |
| PHP full suite | PASS — `php artisan test --compact --no-ansi`: **317 tests, 3.039 assertions** |
| Regression article save | PASS — **18 cases**: native create/update/editor redirects, stale Referer dan manual validation, JSON validation, plain/Markdown/rich HTML/tables/headings/lists/long/unsafe body, unauthorized/guest/CSRF, safe old input, metadata log |
| JS | PASS — `node --test tests/js/*.test.js`: **16 tests**, termasuk scope/expiry/blocked-storage recovery |
| Pint | PASS — `php vendor/bin/pint --test --quiet` |
| Vite | PASS — `npm run build`; manifest/assets dibangun ulang; warning ukuran ApexCharts admin existing >500 KB tetap ada |
| Browser clipboard | PASS — sembilan tipe clipboard nyata melalui Chrome ClipboardItem + Ctrl+V, masing-masing create/edit/reload; tepat satu request per save, edit spoof PATCH |
| Browser failure handling | PASS — validasi Laravel, 403/405/413/419/500 mock, invalid HTML 200, Apache internal ErrorDocument, recovery, dan double submit |
| Preview | PASS — preview server-rendered; artikel published tersedia di HTML publik setelah save |
| Browser CSRF/session | PASS — APIRequestContext dengan token salah (tanpa hint Sec-Fetch-Site) menghasilkan 419; cookie session dihapus pada fetch UI same-origin menghasilkan 401. Editor tidak berpindah dan body tetap tersedia |
| Editor/analyzer/UI | PASS — analyzer satu instance, toolbar tetap bekerja, screenshot 390 px tanpa document horizontal overflow; tidak ada page exception/unexpected console error. Console resource errors untuk status penolakan yang sengaja diuji dikecualikan dari assertion ini |
| Production read-only | PASS terbatas — HEAD admin/login 200, editor guest 302 menuju login, noindex; header melaporkan PHP 8.4.28. Tidak ada authenticated create/edit production |
| Linux Mint dan PHP 8.4 execution patch | NOT VERIFIED — browser QA memakai Chrome/Chromium **Windows**, Apache 2.4.58 dan native PHP **8.3.0**; klaim kompatibilitas source bukan hasil eksekusi PHP 8.4 |

Browser scripts: `tests/browser/seed-article-save.php`, `prepare-article-save-apache.php`, `article-save.mjs`. Seed menolak database selain SQLite `article-save-qa.sqlite`; memakai migrate biasa, bukan migrate:fresh/DemoSeeder. Apache hanya localhost port 8770; header QA block hanya ada di config **QA**, tidak masuk public/.htaccess. Environment DB_CONNECTION/DB_DATABASE harus menunjuk file QA sebelum menjalankan seed. QA membutuhkan compiled assets, bukan marker `public/hot` yang menunjuk dev server; marker lokal disimpan sementara dan dikembalikan sesudah pengujian. PLAYWRIGHT_MODULE dan ARTICLE_SAVE_QA_CHROMIUM menunjuk instalasi existing; tidak ada dependency baru.

Log, screenshot, hasil browser dan database QA berada di `storage/app/article-save-qa`, diabaikan Git. Tidak ada angka/konten fixture yang dimasukkan production.

## File dan deployment cPanel

Source: `PostController`, `HtmlSanitizer`, `LogArticleSaveFailures`, `bootstrap/app.php`, `article-form.js`, `article-editor.js`, modul baru `article-save.js`/`article-draft.js`, Blade form/editor, regression PHP/JS/browser, serta compiled assets/manifest. Routes, PostWriter/revision, app.js, PrivateRobots dan public/.htaccess diaudit dan tidak perlu diubah.

1. Review source dan backup database/uploads sesuai prosedur existing. Build dilakukan lokal; upload/commit manifest **bersama semua aset yang dirujuk**, termasuk chunk baru. Hash aset lama dipertahankan untuk tab yang masih terbuka. Setelah release pengguna perlu reload editor agar memakai handler baru; salin konten terlebih dahulu.
2. Pastikan PHP 8.4, APP_DEBUG=false dan APP_KEY tetap. Tidak ada perubahan `.env` otomatis, migration baru, dependency/token eksternal, Redis, Node runtime production atau worker wajib.
3. Pull dan Deploy HEAD melalui cPanel Git Version Control **secara manual**. `.cpanel.yml` existing tidak diubah; cache clear/build langkah existing tetap berlaku. Jangan menjalankan migrate:fresh atau DemoSeeder. Detail alur hosting tetap mengikuti `docs/deployment-cpanel.md`.
4. Smoke test menggunakan akun berwenang: create draft dengan contoh konten, save, reload, edit, preview; verifikasi request POST + PATCH spoof, JSON sukses, tujuan `/edit`. Uji validasi dan session expiry dengan salinan konten aman, bukan artikel production penting yang belum dicadangkan. Periksa tidak ada error JavaScript.
5. Bila hosting menolak payload, patch menjaga editor tetap terbuka tetapi **tidak dapat membatalkan blokir WAF**. Selesaikan false positive/ErrorDocument dengan provider menggunakan bukti di atas. Jangan menyatakan seluruh kasus production selesai sebelum authenticated smoke test dan audit log hosting tersedia.
