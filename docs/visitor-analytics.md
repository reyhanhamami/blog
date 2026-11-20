# Visitor Analytics & Advertising Insights

Implementasi 20 November 2026. Dashboard: `/admin/analytics`, nama menu **Visitor Analytics**. Seluruh laporan, statistik artikel, CSV, dan halaman cetak hanya untuk role persis `superadmin` yang sudah login. Tidak ada deployment production otomatis.

## Arsitektur

1. Middleware `PublicVisitReceipt` memeriksa response GET HTML 200 dari view publik yang diizinkan. Homepage, artikel published, halaman statis, taxonomy, direktori, video, course/path/quiz publik dapat diberi receipt terenkripsi. Pencarian, akun, autentikasi, admin, preview, resource, XML, error dan HEAD tidak diberi receipt.
2. Cookie first-party `besofton_visitor` berisi 32 byte random, terpisah dari login/session. Laravel mengenkripsi dan mengautentikasi cookie; HttpOnly, SameSite=Lax, host-only, path `/`, umur 90 hari. Secure digunakan pada HTTPS/production. JavaScript tidak membaca identifier cookie.
3. Receipt mengikat hash browser, path publik tanpa query string, post ID bila relevan, dan expiry 24 jam. Receipt tidak berisi counter/statistik, raw IP, user ID, session ID, atau token autentikasi.
4. Modul publik kecil mengirim konfirmasi sesudah dokumen benar-benar tampil (`DOMContentLoaded`, `livewire:navigated`, visible tab, atau BFCache pageshow). Event lifecycle ganda untuk dokumen yang sama tidak mengirim ulang. Setiap aktivasi dokumen memakai UUID random; retry request yang sama dapat dideduplikasi di database.
5. Server memvalidasi receipt, cookie, expiry, CSRF, dan kelayakan kunjungan. Transaksi database menulis ledger deduplikasi, membership harian, daily counters dan counter artikel. Semua angka dashboard dihitung di server. Tidak ada Google Analytics API, token eksternal, fingerprint perangkat, Redis, queue worker permanen, atau Node runtime di production.

Konfirmasi browser dibutuhkan karena Livewire dapat melakukan prefetch sebelum pengunjung benar-benar menavigasi. Menghitung setiap GET akan menghitung prefetch yang dibatalkan; hanya memfilter header prefetch juga tidak cukup karena request navigasi/prefetch Livewire bisa serupa. HTML artikel tetap server-rendered dan tersedia tanpa JS; konfirmasi analytics tidak mengontrol pemuatan konten/SEO.

### Collection endpoint dan otorisasi

`POST /visits/confirm` adalah endpoint koleksi **anonymous write-only**, bukan API laporan. Ini satu-satunya endpoint publik untuk analytics, diperlukan untuk kunjungan guest. Tidak mengembalikan statistik, identifier, hasil validasi receipt, atau data dashboard—respons normal selalu 204 kosong. CSRF web middleware tetap aktif; request cross-site ditolak dari penghitungan. Ada throttle 120 request/menit per signature jaringan framework, serta cooldown per browser/halaman di database.

Semua endpoint **pembacaan** analytics, termasuk `/admin/analytics`, `/admin/analytics/export` dan `/admin/analytics/print`, dilindungi auth/CMS/permission existing **dan** `RequireSuperadmin`. Controller juga memeriksa role sebelum query. Tidak ada analytics Livewire component atau endpoint hidrasi yang mengekspos statistik. Memberi `analytics.view` atau seluruh permission kepada editor/author/admin/custom role tidak melewati pemeriksaan role.

Menu memakai syarat Superadmin selain permission. Dashboard existing hanya mengambil total views untuk Superadmin. Kolom Views dan panel artikel dibungkus role check; data panel juga hanya di-query di controller untuk Superadmin. Atribut analytics Post, termasuk alias periode, disembunyikan dari serialisasi model. CRUD role/permission dan editor lain tetap bekerja seperti sebelumnya.

## Definisi dan rumus metrik

| Metrik | Definisi |
| --- | --- |
| Page Views | Aktivasi halaman publik yang tampil dan lolos validasi, termasuk kunjungan ulang setelah cooldown |
| Article Views periode | Page Views baru yang mempunyai post ID pada periode tercatat |
| Daily Unique Visitors | Estimasi jumlah hash browser berbeda pada satu tanggal WIB, lintas seluruh halaman situs |
| Period Unique Visitors | `COUNT(DISTINCT visitor_hash)` pada membership situs dalam seluruh periode; **bukan penjumlahan daily uniques** |
| Unique Visitors artikel | Distinct hash browser pada artikel itu di seluruh periode, bukan jumlah uniques harian artikel |
| Average Daily Visitors | Jumlah Daily Unique Visitors ÷ jumlah hari tercatat, termasuk hari tanpa trafik |
| Average Daily Page Views | Total Page Views ÷ jumlah hari tercatat, termasuk hari tanpa trafik |
| Monthly Page Views | Views pada irisan bulan kalender dengan periode terpilih |
| Monthly Unique Visitors | Browser berbeda dalam irisan bulan/periode, dihitung distinct ulang; jangan menjumlahkan unique bulanan menjadi unique lintas bulan |
| Growth Page Views | `(current - previous) / previous × 100`, dibanding periode sebelumnya dengan durasi sama |

Contoh matematika: browser yang sama datang pada dua hari, lalu hari ketiga tidak ada trafik: daily uniques `[1,1,0]`, period unique **1**, average daily visitors **2/3**, bukan unique 2. Ini ilustrasi rumus, bukan angka produksi.

Growth ditampilkan unavailable bila cakupan pembanding belum lengkap atau previous=0/current>0; tidak menampilkan infinity. Bila kedua periode tercatat bernilai nol, growth=0. Previous>0/current=0 memberi -100%.

### Periode dan cakupan

Pilihan: hari ini, kemarin, 7/30/90 hari, custom. Rentang custom harus berurutan, tidak di masa depan dan berada dalam 90 hari kalender WIB terakhir. Data sebelum migration/aktivasi tidak direkonstruksi: awal efektif laporan dipotong ke tanggal analytics mulai tersedia. UI/CSV/cetak menyatakan tanggal mulai, periode efektif dan jumlah hari tercatat. Rentang seluruhnya sebelum aktivasi diberi keterangan tidak ada cakupan; ini bukan bukti trafik historis nol.

Grafik utama memasukkan nol untuk setiap hari tanpa trafik **dalam cakupan yang tersedia**. Mini chart artikel 30 hari memakai null sebelum aktivasi, bukan nol historis buatan. Rata-rata panel dihitung dari hari yang memang tercatat.

Seluruh KPI situs, grafik, ranking dan Advertising Insights mengikuti periode efektif. Today's/Yesterday's/Last 7/Last 30 Visitors memakai **irisan** jendela kalender tersebut dengan periode terpilih, dijelaskan pada kartu. Search/kategori/sort merupakan filter **tabel artikel dan baris artikel CSV**; tidak mengubah total situs atau ranking seluruh situs. Counter historis/combined dan Last Viewed At diberi label all-time.

## Aturan hitung, pengulangan, bot, dan privasi

- Receipt dan UUID yang sama hanya dihitung sekali melalui primary key ledger, termasuk request paralel/retry.
- Browser yang sama membuka halaman yang sama dalam **kurang dari 10 detik pada tanggal WIB yang sama** tidak menambah views. Setelah ≥10 detik, refresh/return visit boleh menambah Page Views; browser tetap satu unique untuk hari/periode terkait. Pergantian hari membuat membership harian baru.
- Browser yang sama boleh membaca artikel berbeda segera: setiap artikel mendapat satu view/unique, tetapi situs tetap satu unique. Query/filter URL tidak membentuk identitas halaman baru; page key adalah hash path tanpa query.
- Header Purpose/Sec-Purpose/X-Moz yang menandai prefetch/prerender dikecualikan. Receipt hasil prefetch Livewire yang tidak menjadi dokumen aktif tidak pernah dikonfirmasi. HEAD, asset, sitemap, feed, login, search, admin, draft/future/deleted dan preview tidak dihitung.
- Staff yang login dengan role selain reader dikecualikan, termasuk kunjungan publik pemilik; reader dan guest dapat dihitung. Identified bots/crawlers, headless, Lighthouse/PageSpeed serta klien otomatis umum disaring berdasarkan User-Agent. Ini heuristik, **bukan deteksi seluruh bot** atau sistem anti-fraud iklan.
- DNT=1 dan Sec-GPC=1 menghentikan tracking. Tidak ada third-party tracking baru. Cookie/autentikasi/session token bukan identifier analytics.
- Database analytics tidak menyimpan raw IP, raw User-Agent, email, user ID, referrer, query pencarian, atau fingerprint. Framework rate limiter memakai signature jaringan berumur singkat; analytics identity tetap random cookie. Log web server yang sudah ada merupakan sistem terpisah dan perlu kebijakan retensi hosting sendiri.
- Kebijakan privasi existing (`privacy-policy`) kini mendapat penjelasan first-party analytics saat dirender, tanpa mengubah isi Page tersimpan secara massal. Detail implementasi/retensi ada di dokumen ini; operator perlu memastikan cron sesuai penjelasan kebijakan.

Browser berbeda ≠ orang berbeda: satu orang dengan beberapa browser/perangkat/private windows dapat dihitung lebih dari sekali; browser bersama dapat menggabungkan beberapa orang. Menghapus/blok cookie, memblok JS/beacon, jaringan gagal, DNT/GPC, dan receipt cache yang sudah lebih dari 24 jam dapat mengurangi kelengkapan. Halaman tetap berfungsi ketika tracking gagal. Reload mengambil receipt baru. Tidak ada klaim trafik mencakup seluruh request server.

## Historis dan transisi counter

Migration additive menambah `posts.legacy_views`, `posts.tracked_views`, `posts.last_viewed_at` tanpa mereset `posts.views`. Pada migration, hanya kolom legacy baru yang disalin dari `views` existing; tidak ada tanggal harian atau unique historis yang dibuat. Snapshot tidak mengubah updated_at/published_at.

Counter session-per-artikel lama pada PublicContentController dihentikan agar tidak berjalan bersamaan dengan analytics baru. Setiap konfirmasi yang diterima menambah `posts.views` dan `posts.tracked_views` secara atomik, tanpa menyentuh timestamp editorial. Normalnya combined = snapshot legacy + new tracked views; `posts.views` tetap counter all-time authoritative. Metodologi lama (session view) berbeda dengan Page Views baru, sehingga combined tidak digunakan untuk growth/unique/daily chart atau perbandingan trafik harian.

Operasi editorial save tidak menerima input counter. Duplikasi artikel juga mengecualikan seluruh counter/timestamp analytics, sehingga copy draft baru tidak mewarisi statistik artikel asal. Rollback migration tidak mengurangi `posts.views`; jika migration dijalankan lagi sesudah rollback, histori detail yang sudah dihapus rollback tidak bisa dipulihkan dengan membuat angka baru.

Lakukan cutover dalam release terkoordinasi agar worker source lama tidak terus menulis counter lama setelah snapshot. Jangan menulis counter secara manual. Jika proses di luar tracker mengubah views, selisih dengan legacy+tracked harus diaudit, bukan dimasukkan sebagai daily/unique palsu. Trash tetap masuk total all-time; artikel yang dihapus permanen tidak lagi mempunyai counter Post/ranking, sedangkan daily aggregate yang sudah direkam dipertahankan selama retensinya.

## Schema dan integritas

| Tabel/kolom | Isi dan indeks |
| --- | --- |
| `posts` tambahan | Snapshot legacy, new tracked counter, last_viewed_at UTC; counter lama tetap |
| `analytics_state` | Satu row: started_at UTC dan last_pruned_at UTC |
| `analytics_daily` | Agregat per tanggal WIB + page key; key `site` adalah agregat seluruh situs, key path hash untuk halaman; views, daily visitors, post ID nullable, last_seen_at UTC; PK(date,page_key), index(post_id,date) |
| `analytics_site_visitors` | Membership browser/hari untuk distinct lintas halaman/periode; PK(date,visitor_hash) |
| `analytics_page_visitors` | Membership browser/halaman/hari, views dan last_seen_at untuk cooldown; PK(date,page_key,visitor_hash), index(post_id,date,visitor_hash) |
| `analytics_receipts` | Hash receipt+UUID untuk idempotency; PK(id), index(expires_at) |

Timestamp disimpan sebagai DATETIME UTC literal; tanggal laporan selalu Asia/Jakarta. Timestamp baru tidak bergantung pada timezone sesi MySQL. Tidak ada raw event per refresh yang disimpan selamanya.

Unique constraints mengamankan concurrent membership/idempotency. Transaksi dengan retry deadlock, row lock pada membership/article, serta SQL increments menghindari read-modify-write counter yang lost update. Kegagalan di tengah transaksi membatalkan seluruh rangkaian, termasuk receipt. Duplicate insert memakai data bertipe/tervalidasi dan unique constraints. SQL update counter tidak memicu perubahan timestamp editorial.

## Retensi dan scheduler

Default konfigurasi di `config/analytics.php`:

- Daily membership/detail: **90 hari kalender WIB**, termasuk hari ini. Unique periode hanya didukung dalam jendela ini.
- Ledger receipt: expiry sekitar **24 jam** sejak konfirmasi; physical deletion mengikuti jadwal cleanup.
- Daily aggregates: **1095 hari**. Setelah detail dibuang, aggregate daily views/uniques tetap ada, tetapi unique lintas hari/periode lama tidak bisa diperoleh dengan menjumlahkannya; UI tidak menawarkan period unique di luar 90 hari.
- Counter all-time Post tetap bersama artikel.

Command `analytics:prune` berjalan harian pukul **02:20 Asia/Jakarta** melalui scheduler existing dan withoutOverlapping. Penghapusan memakai batch kecil (500), filter berindeks dan transaksi pendek. Tracking tetap bekerja ketika cron belum aktif, tetapi **retensi fisik memerlukan cron**; tanpa cleanup storage dapat terus bertambah. Dashboard memperingatkan jika cleanup belum berjalan/tertinggal >2 hari. Jangan menganggap TTL logis otomatis menghapus row MySQL.

## Dashboard dan Advertising Insights

Visitor Analytics memakai desain CMS existing: KPI dengan definisi, traffic chart (views/visitors/keduanya), fallback tabel grafik, historical all-time terpisah, Top 10 artikel, kategori, tabel article performance dengan search/filter kategori/sort/pagination dan link editor/publik. Artikel yang belum published tidak diberi link publik palsu. Last Viewed At kosong diberi label belum tercatat.

Kolom Views pada daftar artikel serta panel read-only Statistik Artikel hanya dirender/query untuk Superadmin. Panel menampilkan total, unique 30 hari, views hari ini/7/30 hari, rata-rata dan mini chart. Panel berada **di luar form editor**, tidak menambahkan field counter, dan tidak mengubah SEO Analyzer/WYSIWYG state.

Advertising Insights menampilkan statistik aktual bulanan (irisan periode), average daily views/visitors, growth, top articles/categories. Kategori adalah kategori artikel saat laporan dibuat, bukan rekonstruksi kategori historis. **Article views bukan ad impressions.** Tidak ada demografi, usia, gender, pendapatan, CTR, konversi, harga iklan atau rate card yang dibuat-buat.

CSV berisi identitas Besofton, waktu pembuatan, periode/awal ketersediaan, rumus/definisi, KPI actual, growth/basis, monthly metrics, kategori, daily data dan artikel sesuai filter. Format UTF-8 BOM + delimiter `;`, angka machine-readable; teks berpotensi formula dinetralkan. Export dibaca dalam transaksi; MySQL/MariaDB memakai repeatable-read untuk transaksi export ketika belum ada transaksi aktif. Rows artikel diproses bertahap 200, tanpa memuat seluruh dataset ke memori.

Halaman `/admin/analytics/print` menyediakan laporan HTML print-friendly dengan identitas, periode, data aktual, top articles, monthly/growth dan batas interpretasi. Endpoint dashboard/export/print memakai private, no-store dan noindex admin existing. Tidak ada share link publik atau pengiriman laporan otomatis ke calon pengiklan.

## Performa dan indexing

GET publik hanya menambah pemeriksaan/receipt crypto dan cookie baru bila diperlukan; **tidak menjalankan query agregasi analytics**. Konfirmasi berjalan sesudah halaman tampil melalui request same-origin kecil. Ingestion sinkron berupa transaksi query kecil berindeks, tanpa Redis/worker. Query dashboard memakai daily rollups; distinct periode membaca membership harian terdeduplikasi, bukan jutaan raw refresh events. Beban distinct tetap bertambah sesuai browser×hari×artikel; batas 90 hari dan indeks membatasi cakupan, bukan menjamin kapasitas tak terbatas pada shared hosting.

Tidak ada cache report yang harus menunggu invalidation: request baru membaca data committed terbaru. Pada trafik aktif, dashboard yang sudah terbuka perlu reload/filter untuk menyegarkan angka. CSV transactional membantu konsistensi antarbaris. Waktu query production/EXPLAIN volume besar belum diukur; bila situs tumbuh, monitor slow queries/storage sebelum menaikkan retensi.

ApexCharts existing di-lazy-import hanya bila markup chart ada pada halaman admin yang terotorisasi. Chunk vendor sekitar **543 KB raw /148 KB gzip**, sehingga Vite memberi warning >500 KB; tidak dimuat pada halaman publik atau halaman admin tanpa chart. Modul analytics publik menambah sekitar **0,76 KB raw** pada entry publik (entry akhir sekitar 6,97 KB). Grafik menyediakan ruang layout dan container responsive; tooltip/angka menggunakan format Indonesia. Render failure menampilkan fallback tabel/CSV.

Tracking errors tidak membuat halaman publik 500: receipt/collector menangkap kegagalan dan mencatat `Visitor analytics write failed` dengan tipe exception/kode, tanpa raw request/visitor/token/IP. Respons collector tidak membocorkan exception; browser memberi peringatan singkat jika request gagal. SSR artikel, canonical, meta robots, sitemap, schema, dan tanggal editorial tetap mengikuti fitur SEO existing. Tidak ada klaim skor Lighthouse/CWV production baru dalam pekerjaan ini.

## Bukti pengujian

| Pemeriksaan | Hasil |
| --- | --- |
| `php artisan migrate:status` | PASS; awalnya migration baru Pending, kemudian Ran pada development lokal dan QA |
| Migration additive di development MySQL Wamp | PASS; physical datadir diverifikasi lokal `C:/wamp64/...`, semua nilai views dan updated_at Post sama sebelum/sesudah; tidak menjalankan migration pada production |
| `php artisan test --compact --no-ansi` | PASS — 299 tests, 2.932 assertions pada source final |
| VisitorAnalyticsTest | PASS — 36 cases untuk counter, cookie, dedup/returns, period unique/daily average/zero days, timezone, bot/prefetch, privasi, role/access/leakage, CSV/print/filter, retensi dan additive rollback |
| MySQL concurrency integration | PASS pada MySQL **8.2.0** lokal: 8 worker paralel satu receipt menghasilkan 1 view; 8 browser/receipt berbeda berikutnya menambah tepat 8. Legacy 120 tetap, total 129, tracked/site views 9, daily visitors 9 |
| MySQL report dan CSV | PASS; agregat, join ranking, distinct visitor dan export repeatable-read sesuai sembilan kunjungan committed pada database QA paralel |
| Browser QA | PASS: homepage dan article confirmed display, unused Livewire prefetch tidak dihitung, duplicate lifecycle diabaikan, refresh <10 detik ditolak, return setelah 10,5 detik menambah view tanpa unique baru |
| Browser dashboard/editor | PASS: login Superadmin, grafik, toggle series, filter periode, search/kategori/sort, CSV/print; Views column dan panel di luar form; save editor; staff public visits excluded |
| Browser authorization | PASS: Editor dengan seluruh permission tetap 403 dashboard/export/print dan tidak melihat menu, Views, panel/chart/hydration stats |
| Responsive/console | PASS pada 1440 dan 390 px; screenshot diperiksa, tidak ada document horizontal overflow setelah chart resize; tidak ada page/console JavaScript error |
| `node --test tests/js/*.test.js` | PASS — 14 tests; package existing tidak memiliki script `npm test` |
| `php vendor/bin/pint --test --quiet` | PASS |
| `npm run build` | PASS — Vite 7.3.3; warning ukuran vendor ApexCharts admin dicatat di atas |

PHP lokal native **8.3.0**; source memakai API yang kompatibel dengan target PHP 8.4, tetapi execution source baru pada binary PHP 8.4/cPanel **NOT VERIFIED**. SQLite in-memory dipakai untuk suite feature; MySQL concurrency dan migration juga benar-benar dijalankan pada server lokal terpisah. Database concurrency bernama `besofton_analytics_qa_*`, bukan database production. Tidak menjalankan migrate:fresh atau DemoSeeder. Fixtures/simulated browser traffic hanya pada QA, bukan angka production.

Artefak lokal (log, screenshot, hasil JSON, database preview) berada di `storage/app/visitor-analytics-qa`, diabaikan Git. Scripts yang dapat dijalankan ulang: `tests/browser/seed-visitor-analytics.php`, `tests/browser/visitor-analytics.mjs`, `tests/integration/analytics-concurrency.php`. Seeder browser menolak selain SQLite `visitor-analytics-qa.sqlite`; integration runner hanya menerima server MySQL localhost dan membuat database QA bernama random, tidak menghapus database existing. Jangan jalankan fixture pada hosting.

## Deployment cPanel melalui Git Version Control

1. Backup database dan uploads melalui fasilitas cPanel yang ada. Tinjau source, migration additive, serta `public/build/manifest.json` dan aset hasil build lokal. Pertahankan hash aset lama selama masa transisi tab yang masih terbuka. Jangan unggah database/log/cache/fixtures QA.
2. Pastikan PHP **8.4**, MySQL/InnoDB, APP_ENV=production, APP_DEBUG=false, APP_URL HTTPS benar dan APP_KEY existing tetap. Tidak membutuhkan token analytics, Redis, SSH, Node runtime, atau queue daemon. Tidak ada perubahan .env otomatis.
3. Di **Git Version Control → Manage → Pull or Deploy**, lakukan Update from Remote dan Deploy HEAD Commit **secara manual** saat siap. `.cpanel.yml` existing tetap utuh, menjalankan config:clear → migrate --force --seed → optimize:clear → config:cache → view:cache. Migration baru adalah additive; DatabaseSeeder existing tidak memanggil DemoSeeder. Phase ini tidak membutuhkan demo/data seed baru. Jangan menggunakan migrate:fresh atau mengganti APP_KEY.
4. Pastikan migration `2026_11_20_000001_create_visitor_analytics` selesai sebelum membuka dashboard. Tanggal started_at production ditetapkan saat migration production, bukan tanggal audit/fixture. Periksa bahwa views lama tetap dan daily/unique mulai kosong secara wajar.
5. Aktifkan/periksa cron **setiap menit** melalui cPanel Cron Jobs, sesuaikan path PHP/aplikasi existing:

   ```text
   * * * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/zfoeasqc/public_html/blog/artisan schedule:run >> /dev/null 2>&1
   ```

   Scheduler menjalankan analytics:prune pukul 02:20 WIB. Tanpa SSH, command prune juga dapat dijalankan sekali melalui cron lalu jadwal sekali itu dihapus. Periksa last_pruned_at/dashboard warning dan log bila scheduler gagal.

6. Uji browser guest normal dengan cookie/JS aktif: homepage/article terbuka 200, POST `/visits/confirm` 204, cookie HTTPS Secure/HttpOnly/Lax, refresh cepat tidak menaikkan angka. Staff login dikecualikan; gunakan guest browser terpisah. Verifikasi server dapat menerima POST first-party dan tidak dihalangi WAF; jangan mematikan WAF global.
7. Login Superadmin: verifikasi dashboard, filter/chart/CSV/cetak dan panel editor. Uji Editor/Author/Admin/Reader serta guest melalui direct URL, bukan hanya visibilitas menu. Pastikan tanggal ketersediaan, kebijakan privasi, dan retensi benar.
8. Pantau log `Visitor analytics write failed`, ukuran tabel, rate-limit rejects dan keberhasilan prune. Jangan memasukkan kunjungan monitoring/tes ke materi iklan sebagai trafik organik. Tidak ada auto-submit indexing atau perubahan crawl rules.

Deployment production, runtime PHP 8.4 hosting, performa hosting dalam traffic nyata, scheduler hosting/WAF/Cookie policy final **NOT VERIFIED** sampai operator menyelesaikan smoke test release. Data lab/QA di dokumen ini bukan statistik blog production.
