# Phase 10 — audit indexing dan performa

Tanggal: **18 November 2026, WIB**. Repository: `reyhanhamami/blog`. Production: `https://blog.besofton.id`. Implementasi dilakukan pada checkout lokal; **tidak ada deployment otomatis**.

Status **PASS** berarti ada bukti pada cakupan yang disebutkan; **FAIL** berarti masalah teramati; **NOT VERIFIED** berarti bukti belum tersedia. PASS lokal tidak berarti source terbaru sudah berjalan di production.

## 1. Baseline PageSpeed dan metode

Baseline dari pengguna: mobile sekitar 72, desktop sekitar 75; tanggal, URL, versi Lighthouse, dan konfigurasi laporan tersebut tidak diberikan. Angka itu dicatat sebagai konteks, bukan pengukuran before yang identik dengan run berikut.

Audit menjalankan Lighthouse **13.6.0**, Chromium/Chrome **157**, Windows, headless, cold browser profile per invocation, simulated throttling. Setiap kombinasi halaman/perangkat memiliki **tiga run**, bukan satu skor terbaik. Tidak ada runtime error atau run warning pada 24 laporan utama. Median dihitung terpisah untuk setiap metrik. Data tiap run dan konfigurasi tersimpan dalam [seo-performance-benchmarks.json](seo-performance-benchmarks.json).

Mobile: viewport 412×823, DPR 1,75, RTT 150 ms, throughput 1638,4 Kbps, CPU slowdown 4×. Desktop: 1350×940, DPR 1, RTT 40 ms, throughput 10.240 Kbps, CPU slowdown 1×. Ini Lighthouse CLI lab; bukan PSI server Google dan bukan data field CrUX. Kondisi jaringan internet/font serta beban mesin dapat memengaruhi hasil.

**Production sebelum release source baru:**

| Halaman | Perangkat | Tiga skor performance | Median performance | Median LCP | Median CLS | Median TBT |
| --- | --- | --- | --- | --- | --- | --- |
| Homepage | Mobile | 62 / 62 / 62 | 62 | 20,860 s | 0 | 70 ms |
| Homepage | Desktop | 78 / 79 / 78 | 78 | 3,591 s | 0,0073 | 0 ms |
| Artikel doorway effect | Mobile | 71 / 71 / 70 | 71 | 4,747 s | 0 | 22 ms |
| Artikel doorway effect | Desktop | 96 / 99 / 96 | 96 | 1,055 s | 0,0291 | 0 ms |

Accessibility, Best Practices, dan SEO production masing-masing **100** pada seluruh 12 run ini. Skor SEO lab tidak membuktikan Google sudah menemukan atau mengindeks situs.

## 2. Root cause indexing: fakta dan hipotesis

**PASS — akses dasar dari lokasi audit:** homepage dan artikel tidak membutuhkan login, memiliki canonical HTTPS, `index,follow`, dan konten artikel hadir pada HTML server. Seluruh **23 URL** dalam snapshot sitemap production merespons **200 HTML, tanpa redirect**. Robots, sitemap, dan feed juga 200 dengan MIME sesuai. Tidak ditemukan login interstitial atau HTML error pada XML yang diunduh.

**FAIL — kualitas sitemap existing:** lima arsip kosong masih indexable dan tercantum: `/kategori/bisnis`, `/tag/bisnis`, `/tag/pos`, `/topik/bisnis`, `/topik/penjualan`. Responsnya 200 tetapi menampilkan empty state. Homepage lastmod berubah setiap request. Lastmod artikel contoh juga berubah setelah pembacaan karena penghitung views memakai Eloquent timestamps.

**NOT VERIFIED — alasan Google belum mengenal URL:** informasi “URL tidak dikenal oleh Google”, sitemap perujuk tidak ada, dan histori crawling tidak ada berasal dari pengguna. Tidak tersedia akses akun Search Console, log verified Googlebot, atau histori sitemap submission untuk mengkonfirmasi penyebab. Hipotesis utama adalah discovery/submission belum diproses atau property yang diperiksa tidak mencakup canonical yang tepat. Ini belum terbukti; kualitas sitemap yang buruk juga belum terbukti sebagai penyebab langsung “URL unknown”.

HTTPS awalnya tidak dapat diakses melalui web tool/jaringan sandbox. Akses read-only dengan jaringan yang diizinkan kemudian berhasil; kegagalan awal bukan bukti outage hosting.

## 3. Robots, sitemap, dan feed

| Endpoint production saat audit | Status | Content-Type | Redirect |
| --- | --- | --- | --- |
| `/` | PASS — 200 | `text/html; charset=utf-8` | 0 |
| `/robots.txt` | PASS — 200 | `text/plain; charset=utf-8` | 0 |
| `/sitemap.xml` | PASS — 200, XML dapat diparse | `application/xml` | 0 |
| `/feed.xml` | PASS — 200, RSS | `application/rss+xml` | 0 |
| `/lupa-saat-masuk-ruangan-doorway-effect` | PASS — 200 | `text/html; charset=utf-8` | 0 |

Sampel TTFB GET endpoint tersebut sekitar 0,36–0,44 detik pada batch awal; ini sampel jaringan, bukan SLA/server p95. Semua URL pada XML memakai domain HTTPS yang benar; tidak ditemukan localhost/domain lain pada loc.

**PASS — source dan tests sitemap baru:**

- `Post::published()` tetap mensyaratkan status published, `published_at <= now()`, dan SoftDeletes. Noindex dikeluarkan. Scheduled yang belum diterbitkan scheduler tidak dibuat publik oleh sitemap.
- Kategori harus aktif/nondeleted dan mempunyai artikel published indexable. Tag, topik, serta author harus mempunyai artikel published indexable. Arsip tanpa artikel indexable dirender noindex; kategori nonaktif tetap 404.
- Halaman statis published, video terbit, course/path/quiz published, serta direktori publik yang mempunyai konten disertakan. Login, akun, CMS, draft/preview, pencarian dan private progress tidak dimasukkan. Pelajaran course tidak ditambahkan massal: sebagian hanya berupa link materi/progres; course dan konten primer tetap menjadi jalur discovery.
- Route root yang berbenturan dengan slug artikel/page dikeluarkan. Page published yang memang dilayani lebih dahulu tidak diduplikasi sebagai Post. Test mengunjungi seluruh loc fixture dan memastikan 200/indexable.
- Lastmod artikel/video memakai waktu model yang relevan, minimal publication time; page/course/path/quiz memakai updated_at. Homepage/direktori/arsip komposit tidak mengarang lastmod ketika tidak ada jam perubahan yang dapat diandalkan. Tidak ada perubahan tanggal konten untuk menyegarkan sitemap.
- Di bawah **5.000 URL** tetap satu urlset. Saat melampaui batas konservatif tersebut, `/sitemap.xml` otomatis menjadi index dan `/sitemaps/{kind}/{page}.xml` memuat maksimal 5.000 URL dengan kolom database minimal. Jenis/page shard tidak valid memberi 404. Tidak mengambil seluruh tabel beserta content HTML ke memori. Dengan panjang slug database saat ini, batas ini juga jauh di bawah 50 MB. Batas resmi satu sitemap adalah [50.000 URL/50 MB uncompressed](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).

Feed tetap RSS publik, kini mengecualikan noindex dan hanya membaca kolom yang dibutuhkan; layout menyediakan tautan alternate RSS. Robots production tetap melarang `/admin`. `/cari` tidak lagi diblokir robots agar crawler dapat membaca noindex pada hasil pencarian; [robots blocking tidak menggantikan noindex](https://developers.google.com/search/docs/crawling-indexing/block-indexing). Tidak ada perubahan middleware autentikasi/permission.

## 4. Canonical dan meta robots

| Jenis halaman | Perilaku source baru | Bukti |
| --- | --- | --- |
| Homepage, published Post | index,follow, canonical tunggal HTTPS | PASS tests; production lama juga PASS |
| Post noindex | noindex,nofollow; tidak di sitemap/feed | PASS tests |
| Kategori/topik/tag/author berisi konten | index,follow | PASS tests |
| Arsip kosong | noindex,follow; tidak di sitemap | PASS tests |
| Kategori nonaktif | 404 | PASS tests |
| Filter kategori | noindex,follow; canonical arsip utama | PASS tests |
| Pagination arsip | canonical diri sendiri dengan `?page=N` | PASS tests |
| Artikel dengan query `page` | canonical tetap artikel utama | PASS tests |
| Search | noindex,follow | PASS tests |
| Login/register/password/akun/CMS/preview | noindex; X-Robots-Tag untuk path privat | PASS tests; auth tetap melindungi |
| Staging/non-production | robots Disallow `/`, meta noindex | PASS tests |

URL generator production memakai APP_URL existing dan memaksa scheme HTTPS, termasuk di belakang TLS proxy, tanpa mempercayai header proxy/User-Agent sembarangan. Nilai APP_URL hosting tetap harus benar; source tidak menulis `.env`.

Metadata OG sebelumnya jatuh ke nama/deskripsi situs pada banyak halaman berbeda. Kini fallback OG dan Twitter mengikuti title/description halaman, OG image dinormalisasi menjadi URL absolut, dan hanya satu canonical dirender. SEO title khusus artikel dipakai **persis** tanpa suffix otomatis; fallback judul artikel tetap dapat memakai suffix satu kali. Contoh title production lama panjang **74 karakter**; bagian editorialnya **54 karakter**. Tidak ada pemotongan judul berdasarkan batas karakter dan tidak ada perubahan artikel di database. Title/metadescription tetap dapat disunting editor.

Redirect slug lama tetap memakai mekanisme existing 301; regresinya dicakup suite. Validitas seluruh histori redirect production **NOT VERIFIED**, karena tabel redirects tidak diakses.

## 5. Structured data

**PASS lokal:** JSON-LD dapat diparse sebagai JSON valid; Organization/WebSite memakai identitas dan origin Settings/URL generator yang sama. Article memakai judul yang terlihat, author sebenarnya beserta URL author bila tersedia, description fallback publik, publication time, editorial updated_at, canonical artikel, dan image absolut. BreadcrumbList sekarang mengikuti breadcrumb HTML termasuk kategori aktif, tanpa menautkan kategori nonaktif yang menghasilkan 404. Penghitungan views tidak lagi memalsukan dateModified. Tanggal historis yang sudah terpengaruh views tidak ditulis ulang: sumber editorial historisnya belum terverifikasi.

**Perbaikan video:** model existing mempunyai tanggal publikasi entri CMS, tetapi tidak menyimpan tanggal pertama video YouTube dipublikasikan. Nilai itu tidak dapat dibuktikan sebagai `VideoObject.uploadDate`, yang [harus merupakan tanggal pertama video dipublikasikan](https://developers.google.com/search/docs/appearance/structured-data/video). Source kini menggunakan **WebPage** dengan datePublished entri CMS dan thumbnail, sehingga tidak mengklaim metadata video yang belum diketahui. VideoObject bisa diaktifkan dalam pekerjaan berikutnya setelah tanggal video yang terverifikasi dan persyaratan watch page tersedia. Tidak ditambahkan duration/contentUrl/penilaian palsu.

Course rich-result schema tidak ditambahkan: model course existing belum menjadi bukti bahwa seluruh kebutuhan markup terkait dipenuhi. Tidak ada FAQPage/HowTo massal. Production JSON-LD Article/Organization/WebSite/Breadcrumb dapat dibaca dari source existing; validasi rich result Google, akses seluruh thumbnail YouTube, dan eligibility rich results setelah release **NOT VERIFIED**.

## 6. Internal linking dan SEO Analyzer

**PASS source/tests:** homepage → artikel, kategori/topik/tag/author → artikel, related articles, breadcrumb, previous/next, direktori pembelajaran dan pagination memakai `<a href>` biasa. `wire:navigate` tetap mempunyai href; crawler tidak harus mengklik Livewire untuk menemukan atau membaca artikel. Navigasi previous/next existing memberikan jalur ke artikel di luar batas kartu homepage. Related topic kini harus berbagi artikel published indexable, bukan seluruh topik alfabetis yang tidak berkaitan. Tidak ditambahkan tautan editorial secara massal.

Konten artikel contoh ditemukan di HTML awal production. Orphan audit terhadap seluruh database/tautan eksternal historis **NOT VERIFIED**; snapshot production hanya mempunyai dua artikel sitemap. Algoritma analyzer sebelumnya memang menghitung suffix yang dirender; masalahnya kebijakan suffix membuat title khusus lebih panjang. Analyzer dan preview kini mengikuti kebijakan title yang sama dengan Blade, dengan test panjang/title fallback dan title khusus. Saran panjang tetap panduan, bukan pemotongan otomatis.

Rekomendasi keyphrase H2/H3, frekuensi, serta tautan internal tetap saran kualitas. Focus keyphrase tersimpan dan kondisi form CMS artikel contoh tidak diakses dalam audit (**NOT VERIFIED**); tidak mengklaim seluruh empat indikator screenshot kini hijau. Konten artikel tidak diedit/ditambah kata kunci. Rekomendasi harus dinilai editor berdasarkan intent dan relevansi.

## 7. LCP, CLS, INP

**FAIL baseline LCP mobile:** Lighthouse mengidentifikasi hero homepage sebagai LCP, sedangkan artikel mobile memakai H1. Hero sudah eager/high priority di source existing; menambah preload gambar yang sama tidak menyelesaikan payload besar. Hero dan featured detail tetap tersedia langsung di HTML dan tidak di-lazy-load; kartu di bawah viewport tetap lazy. Thumbnail utama video tidak lagi lazy.

**PASS CLS lab yang diukur:** median baseline dan preview berada di bawah 0,1. Dimensions logo/hero/detail memakai metadata file lokal ketika tersedia. Screenshot preview memperlihatkan featured image artikel mobile menjadi terlalu tinggi karena HTML height berkompetisi dengan CSS width. CSS detail kini memakai `height:auto` dan `object-fit:contain` agar rasio asli terlihat tanpa pemotongan/gepeng. Screenshot mobile/desktop setelah koreksi diperiksa; hero collage dan typography existing dipertahankan. Embed video tetap mempunyai ruang aspect-ratio sebelum iframe dimuat.

**NOT VERIFIED INP/CrUX:** TBT di tabel adalah metrik lab, bukan INP. Tidak ada data field p75 atau real-user interaction trace yang dapat membuktikan INP ≤200 ms. Navbar, filter, kuis, bookmark/form, newsletter, dan navigasi tetap memakai dependency interaktif existing. Runtime JavaScript halaman utama tidak menunjukkan console error pada laporan lokal, tetapi ini bukan bukti seluruh interaksi sudah diuji lewat klik.

## 8. Optimasi gambar dan pengukuran ukuran

Lighthouse production mengukur hero **2.061.214 byte**, logo **953.578 byte**; perkiraan peluang penghematan image delivery sekitar 2,97 MiB. File logo 2048×2048 dipakai pada area kecil; hero 1424×1024 juga terlalu besar untuk tampilannya. Kedua file tidak memakai TTL cache yang teramati.

**PASS pada salinan lokal dari byte production yang sama:**

| File | Original | WebP 320 px | WebP 640 px | WebP 960 px | WebP 1280 px |
| --- | --- | --- | --- | --- | --- |
| Hero PNG | 2.061.214 B | 13.294 B | 34.050 B | 57.198 B | 80.368 B |
| Logo PNG | 953.578 B | 10.402 B | 27.398 B | 48.934 B | 71.938 B |
| Featured article WebP | 86.898 B | 11.490 B | 27.728 B | 44.364 B | 69.574 B |

Browser memilih ukuran menurut sizes/DPR; run mobile nyata memilih hero 960 px, bukan selalu 640 px. Ini penghematan file terukur, bukan estimasi skor production. Konversi GD menjaga rasio dan alpha; tidak melakukan crop. Unit/feature test memeriksa ukuran, transparansi, hash original tetap, fallback eksternal, dan penolakan traversal.

Gambar header/footer, hero, featured, card dan detail mendapat srcset **hanya jika** file varian tersedia. `src` tetap URL original untuk kompatibilitas. Media/logo baru mencoba membuat varian saat upload; `media:optimize-public` menyediakan backfill untuk file existing melalui cPanel Cron. Tidak ada konversi pada request pembaca, remote image fetch, library baru, atau penghapusan original. Dukungan GD WebP dan hasil backfill production **NOT VERIFIED**; tanpa itu website tetap memakai original.

Favicon production sekitar 1,9 KB, sehingga tidak diubah. Gambar inline content belum diretrofit massal: HTML sanitizer tetap menjaga keamanan dan dimensions yang tersedia. Gambar eksternal/missing dimension dan file yang dilewati karena animasi/EXIF/memori tetap menjadi review editorial. AVIF tidak dipaksakan pada shared hosting.

## 9. CSS, JS, font, dan cache aset

**PASS build/source:** font Outfit tetap memakai range variable existing (CSS menggunakan berat antara seperti 550/650) dan `display=swap`. CSS Google Fonts sekarang ditemukan dari head HTML dengan preconnect ke dua origin font, menggantikan rantai CSS `@import`. Tidak ada preload font/gambar berlebihan atau perubahan identitas font.

Prism stylesheet dipisah dari bundle publik menjadi CSS **1,31 KB**, hanya dimuat untuk konten berkode; grammar tambahan hanya diimpor untuk bahasa yang ditemukan, dengan dependency PHP markup-templating berurutan. Penanda blok mencegah highlighting ulang saat init/navigation. Bundle publik utama berubah dari **6.870 B menjadi sekitar 6,21 KB**, CSS dari **71.261 B menjadi sekitar 69,93 KB**. Ini pengurangan kecil; payload gambar dan Livewire hosting jauh lebih dominan. Swiper tetap khusus homepage dengan >1 featured; bundle CMS tidak ditambahkan ke publik. Listener form/copy existing tetap terjaga.

**FAIL baseline hosting:** stylesheet dan Livewire yang terukur tidak dikompresi, static assets tidak memperlihatkan Cache-Control/Expires. Production mengirim `livewire.js` sekitar **554 KB**; preview dengan APP_DEBUG=false mengirim `livewire.min.js` sekitar **238 KB** uncompressed. Pemilihan tersebut bergantung pada konfigurasi app.debug di kode vendor; konfigurasi environment/config cache production sebenarnya **NOT VERIFIED**. Perbaikan operasional APP_DEBUG/cache dan kompresi harus diverifikasi oleh operator, tidak diubah otomatis.

Source `.htaccess` memberi TTL immutable untuk aset hash Vite/varian WebP dan deflate teks bila modul tersedia. Header final pada nginx/cPanel setelah release **NOT VERIFIED**. Tidak ada cache HTML sesi/auth/admin publik. Aset hash build sebelumnya dipertahankan untuk kompatibilitas tab lama selama pergantian manifest.

## 10. Query/database dan cache

**PASS tests:** tiga Settings lookup berbeda kini cukup **satu SQL read pada cold cache**, kemudian satu snapshot cache per request melalui memo store Laravel; sebelumnya setiap key cold memakai query sendiri dan warm file cache masih dibaca per lookup. Cache bulk dibatalkan saat model disimpan/dihapus, dan default caller tidak tersimpan sebagai nilai key yang sebenarnya belum ada. Test mencakup edit, delete dan dua default berbeda.

HomepageContentService, ContentDiscoveryService, detail dan related posts sudah memakai eager loading yang relevan; indeks status/published_at/category/author sudah ada. Tidak ditambahkan Redis, migration indeks spekulatif, atau full-page/personalized cache. Sitemap tidak lagi `all()` seluruh taxonomy tanpa filter dan feed tidak membaca body artikel yang tidak digunakan. Tidak ada pengukuran SQL profiler/EXPLAIN terhadap database production; durasi query production dan kebutuhan indeks tambahan **NOT VERIFIED**.

## 11. File yang berubah

| Kelompok | File |
| --- | --- |
| Crawlability | `app/Services/PublicSitemap.php`, `app/Http/Controllers/PublicContentController.php`, `routes/web.php`, `resources/views/public/sitemap.blade.php` |
| URL/meta/private | `app/Support/PublicSeo.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/PrivateRobots.php`, `bootstrap/app.php`, `resources/views/public/layout.blade.php`, `post.blade.php`, `explore.blade.php`, `archive.blade.php`, `resources/views/admin/layout.blade.php` |
| Gambar | `app/Support/PublicImage.php`, `app/Console/Commands/OptimizePublicImages.php`, `app/Http/Controllers/Admin/MediaController.php`, `SettingsController.php`, `resources/views/public/home.blade.php`, `video.blade.php`, partial `card`, `home-card`, `home-featured`, `site-header`, `site-footer` |
| Performa | `app/Models/Setting.php`, `resources/css/public.css`, `resources/js/public.js`, `resources/js/public-code.js`, `public/.htaccess` |
| Analyzer | `resources/js/admin/seo-analyzer.js`, `seo-analyzer-ui.js` |
| Tests | `tests/Feature/SeoReadinessTest.php`, `HardeningTest.php`, `tests/js/seo-analyzer.test.js` |
| Build | `public/build/manifest.json` dan aset hash baru yang direferensikan manifest; aset lama dipertahankan |
| Docs | dokumen ini, `docs/google-indexing-guide.md`, `docs/seo-performance-benchmarks.json`, `docs/deployment-cpanel.md` |

`.cpanel.yml`, dependency manifests/locks, database production, dan `.env` tidak diubah. Snapshot/log/SQLite/gambar preview disimpan hanya dalam lokasi audit lokal yang diabaikan Git, bukan artefak deploy.

## 12. Tests dan validasi

| Pemeriksaan | Hasil |
| --- | --- |
| `php -d xdebug.mode=off artisan test --compact` | **PASS — 260 tests, 2.720 assertions** |
| Seluruh `tests/js/*.test.js` melalui Node native test runner | **PASS — 14 tests**, termasuk 12 analyzer checks |
| `npm.cmd run build` | **PASS — Vite 7.3.3, 167 modules**, build final 2,84 s |
| Manifest dan asset hasil build | **PASS — 24 entries**, tidak ada file rujukan hilang |
| `git diff --check` | **PASS** |
| HTTP production baseline | **PASS** endpoint utama dan 23 loc sitemap dalam snapshot |
| Lazy Prism PHP pada preview berkode | **PASS** tiga run: CSS/core/PHP/markup-templating 200, tanpa console error; performance eksploratif 84/83/83, median 83, tidak memiliki baseline pembanding |

Tests memakai Windows native PHP **8.3.0**, SQLite in-memory, cache/session array sesuai phpunit.xml. Preview memakai SQLite file terpisah. Tidak ada migration/seeding terhadap database production. Eksekusi source baru di PHP **8.4 hosting NOT VERIFIED**; respons production existing mengumumkan PHP 8.4.27. Tidak ada dependency/syntax baru di luar kompatibilitas PHP yang sudah dipakai.

Coverage mencakup homepage SSR, published/noindex/draft/scheduled/future/deleted, taxonomy aktif/kosong, page/video/course/path/quiz published, bentrok slug/route, validitas XML, index/shard, robots/staging, canonical HTTPS/pagination, auth/private/preview, metadata fallback/JSON-LD, cache invalidation, varian gambar, serta suite CMS/editor/reader/learning existing. Seluruh URL sitemap fixture dicek sebagai 200 dan tidak noindex. Regresi perubahan title khusus disesuaikan dengan kontrak baru; test VideoObject diganti untuk menolak tanggal upload yang belum terbukti.

Browser UI provider gagal dua kali saat inisialisasi (`node_repl kernel exited`, `windows sandbox ... setup refresh had errors`). Screenshot Lighthouse dipakai untuk verifikasi visual read-only homepage/artikel mobile dan desktop. Klik mobile menu, clipboard, filter navigation, newsletter, bookmark, kuis, dan editor browser secara end-to-end tetap **NOT VERIFIED**; perlu smoke test operator.

## 13. Preview setelah optimasi, bukan production after

Preview: native PHP 8.3 development server di loopback, SQLite fixture, APP_ENV=local, APP_DEBUG=false, build Vite, salinan gambar publik production dengan varian GD. Fixture mempunyai dua featured posts (Swiper aktif), teks/meta tidak identik dengan production. Server lokal tidak menerapkan aturan Apache/nginx. Sebagian suite otomatis berjalan pada mesin yang sama selama rangkaian preview, sehingga hasil bersifat eksploratif dan tidak dianggap benchmark before/after yang terkontrol.

| Halaman | Perangkat | Tiga skor performance | Median | Median LCP | Median CLS | Median TBT |
| --- | --- | --- | --- | --- | --- | --- |
| Homepage | Mobile | 86 / 86 / 86 | 86 | 4,131 s | 0,0016 | 0 ms |
| Homepage | Desktop | 99 / 99 / 99 | 99 | 0,880 s | 0,0073 | 0 ms |
| Artikel | Mobile | 92 / 93 / 69 | 92 | 2,605 s | 0,0037 | 16 ms |
| Artikel | Desktop | 100 / 100 / 100 | 100 | 0,467 s | 0,0292 | 0 ms |

Run artikel mobile ketiga memiliki TBT sekitar **2.688 ms** dan skor 69; tetap disertakan. Penyebab long task belum dipastikan; beban mesin saat tests adalah faktor yang mungkin. Tidak menghapus run tersebut demi skor target. Accessibility/Best Practices lokal 100; SEO lokal **69** karena preview memang noindex. Tests environment production memeriksa bahwa noindex tersebut tidak terbawa ke halaman published production.

**NOT VERIFIED — skor production after dan target final.** Target aspiratif mobile ≥85/desktop ≥90 belum dibuktikan pada hosting dengan source baru. Bahkan median LCP mobile preview belum ≤2,5 detik untuk kedua halaman. Field LCP/INP/CLS p75 dan ketersediaan CrUX belum diperiksa. Penghematan file pada byte original yang sama di bagian 8 adalah perbandingan terkontrol yang tersedia; perbedaan skor antarlingkungan bukan bukti kausal perbaikan production.

## 14. Pemeriksaan production yang belum terverifikasi

- Search Console ownership/property, submission sitemap, Test Live URL/Request Indexing, last crawl, Google-selected canonical, Page Indexing/Crawl Stats.
- Verified Googlebot dari IP resmi; request memakai string UA Googlebot mendapat 200, tetapi **bukan bukti** request berasal dari Google. ModSecurity/WAF/Cloudflare policy, rate limits dan DNS/TLS seluruh resolver/IPv6 belum diaudit. Tidak ada whitelist/bypass WAF yang dibuat.
- Stabilitas intermittent 403/429/5xx di luar jendela audit; tidak ada status tersebut teramati pada batch yang berhasil.
- Source baru di hosting, GD WebP/backfill, file permissions/disk quota, actual cache/compression nginx, APP_DEBUG/config cache, PHP 8.4 runtime tests.
- Semua redirect historis, seluruh orphan content database, eligibility Rich Results/video thumbnail accessibility, interaksi browser manual dan field CWV.

## 15. Search Console dan release cPanel

Ikuti [panduan Google indexing](google-indexing-guide.md) untuk memilih property, submit sitemap.xml, live test homepage/artikel, meminta indexing bila memenuhi syarat, dan memantau Page Indexing. Token memakai Settings existing. Tidak ada Google Indexing API untuk artikel blog dan tidak ada jaminan indeks.

Ikuti [deployment cPanel Phase 10](deployment-cpanel.md#release-phase-10--seo-dan-performa). Upload source serta build; tinjau task existing secara manual. Gambar existing membutuhkan backfill varian lewat satu kali cPanel Cron untuk memperoleh penghematan; tanpa itu original tetap dilayani. Verifikasi config production, kompresi/cache static, endpoint dan smoke tests. Jangan mengunggah fixture audit atau menjalankan DemoSeeder/migrate:fresh. Tidak membutuhkan Node runtime, Redis atau SSH.

## 16. Remaining issues dan release decision

**PASS — implementasi lokal siap ditinjau** setelah tests/build yang dicatat. Hambatan source yang terbukti sudah diperbaiki tanpa redesign atau suntingan massal artikel.

**NOT VERIFIED — release production, target performa hosting, dan indexing Google.** Keputusan: siap untuk **release manual bertahap dengan smoke test**, bukan klaim production selesai. Operator perlu mengkonfirmasi APP_DEBUG/cache, GD/backfill dan header static di hosting, menjalankan interaksi manual yang belum terverifikasi, kemudian mengulang tiga run Lighthouse pada URL production dan menjalankan alur Search Console. Jika hosting tidak mendukung konversi/cache, website tetap berfungsi dengan fallback original, tetapi peningkatan ukuran/score production belum bisa dinyatakan berhasil. Google tetap menentukan discovery, crawling, canonical selection dan indexing.
