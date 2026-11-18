# Google indexing — panduan operasional Besofton Insights

Tanggal audit: 18 November 2026 (WIB). Domain: `https://blog.besofton.id`.

## Verification melalui Settings existing

Jika memakai URL-prefix property, salin **nilai token** dari metode HTML tag Search Console ke CMS **Settings → Token verifikasi Google** (`google_verification`). Jangan tempel seluruh elemen `<meta>`. Layout publik sudah merender tag ini pada server; perubahan source/build tidak diperlukan. Biarkan token tetap tersimpan setelah verifikasi. Domain property menggunakan verifikasi DNS sesuai petunjuk Google, sehingga tidak selalu membutuhkan meta tag. Pada snapshot production audit ini, meta verification tidak terlihat; metode verifikasi property sebenarnya **NOT VERIFIED**.

## Langkah Search Console

1. Masuk ke [Google Search Console](https://search.google.com/search-console) menggunakan akun pengelola.
2. Pilih property yang mencakup `blog.besofton.id`. Untuk URL-prefix, pastikan tepat `https://blog.besofton.id/`; jangan memilih property HTTP atau domain lain.
3. Buka **Sitemaps**.
4. Submit `sitemap.xml`, atau URL lengkap `https://blog.besofton.id/sitemap.xml` bila formulir memerlukannya. Periksa status fetch dan parsing. Aplikasi tetap memakai URL ini walaupun nanti otomatis menjadi sitemap index. Tidak perlu submit setiap shard secara manual. Lihat [Sitemaps report](https://support.google.com/webmasters/answer/7451001).
5. Buka **URL Inspection**, lalu masukkan `https://blog.besofton.id/`.
6. Jalankan **Test Live URL**. Periksa page fetch berhasil, crawling diizinkan, indexing diizinkan, HTML berisi konten, dan resource penting dapat dimuat. Lihat [URL Inspection](https://support.google.com/webmasters/answer/9012289).
7. Jika URL memenuhi syarat, gunakan **Request Indexing**. Lakukan juga untuk artikel contoh `https://blog.besofton.id/lupa-saat-masuk-ruangan-doorway-effect`. Ini permintaan pemrosesan, bukan jaminan indeks. Hindari mengulang permintaan URL yang sama tanpa perubahan atau diagnosis baru.
8. Buka **Page Indexing**. Bedakan unknown/discovered, crawled but not indexed, blocked by robots, excluded by noindex, duplicate/canonical, soft 404, dan server errors. Cocokkan tanggal crawl dengan tanggal deploy. Panduan diagnosis: [Page indexing report](https://support.google.com/webmasters/answer/7440203).
9. Pantau Sitemaps, discovery, Crawl Stats di Settings, serta log akses/error cPanel. Pada URL Inspection versi indexed, bandingkan **user-declared canonical** dan **Google-selected canonical**. Live test tidak memastikan semua keputusan canonical/indexing. Jika ada 403/429/5xx, catat URL, waktu WIB, status, dan request ID; minta hosting memeriksa ModSecurity/WAF/rate limit yang spesifik. Jangan whitelist berdasarkan User-Agent saja atau menonaktifkan WAF global.
10. Evaluasi setelah Google memproses permintaan. Catat tanggal submit, last read sitemap, last crawl, alasan exclusion, dan perubahan jumlah URL. Hasil dapat berubah selama pemrosesan; gunakan data Search Console untuk menilai kemajuan, bukan hanya pencarian `site:`. Google tidak menjamin waktu maupun keberhasilan indexing: [crawling/indexing FAQ](https://developers.google.com/search/help/crawling-index-faq).

## Verifikasi HTTP setelah release

Periksa GET dan HEAD untuk `/`, `/robots.txt`, `/sitemap.xml`, `/feed.xml`, dan artikel contoh. URL publik normal harus 200 tanpa autentikasi, tanpa redirect loop, tanpa HTML error di XML. Periksa `Content-Type`, `X-Robots-Tag`, canonical tunggal HTTPS, dan `<meta name="robots">` pada **source HTML**, bukan hanya DOM setelah JavaScript berjalan. Cari juga URL localhost/HTTP/domain salah. Periksa HTTP→HTTPS pada hosting tanpa mengubah metode POST Livewire.

Contoh read-only dari Windows:

```powershell
curl.exe -I https://blog.besofton.id/
curl.exe -sS -L --max-redirs 5 https://blog.besofton.id/robots.txt
curl.exe -sS -L --max-redirs 5 https://blog.besofton.id/sitemap.xml
curl.exe -sS -L --max-redirs 5 https://blog.besofton.id/feed.xml
```

Robots production membolehkan halaman publik, tetap melarang `/admin`; pencarian internal tetap `noindex,follow` dan dapat dicrawl agar Google dapat membaca instruksi tersebut. Auth dan permission tetap melindungi CMS/akun/preview. Staging/non-production tetap menolak crawling dan merender noindex. [Google menjelaskan bahwa noindex harus dapat dicrawl agar terbaca](https://developers.google.com/search/docs/crawling-indexing/block-indexing).

Sitemap adalah daftar canonical indexable, bukan daftar seluruh endpoint. Artikel draft/scheduled/future/deleted/noindex, arsip kosong/nonaktif, login, pencarian, akun, preview, dan dashboard tidak boleh dimasukkan. Homepage dan arsip komposit sengaja tidak memiliki `lastmod` ketika tidak ada jam perubahan konten yang dapat diandalkan; tanggal tidak dibuat dari waktu request. Lihat [ketentuan sitemap dan lastmod](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap).

Tidak ada auto-submit/ping atau integrasi Google Indexing API untuk artikel blog. [Indexing API dibatasi untuk JobPosting atau BroadcastEvent dalam VideoObject](https://developers.google.com/search/apis/indexing-api/v3/quickstart).

## Catatan pencatatan

| Tanggal WIB | URL/property | Sitemap last read | Live fetch | Indexing allowed | Canonical Google | Last crawl/status | Tindakan berikutnya |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Isi saat pemeriksaan | | | | | | | |

Audit tidak membuka akun Search Console, mengirim sitemap, atau meminta indexing atas nama pengelola. Status tindakan tersebut **NOT VERIFIED** sampai pengelola mencatat hasilnya.
