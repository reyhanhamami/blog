# Besofton Insights — pre-production cleanup and QA

Tanggal: 2026-09-26. Tidak ada deployment, perubahan DNS, atau reset database.

## Safety checkpoint dan cleanup

- Sebelum cleanup, Git working tree bersih pada `ffb68ca`. Cleanup terlacak pada commit `cafa9ed`; perubahan hardening berikutnya masih terlihat melalui `git status`.
- [Inventory per berkas](cleanup-inventory.csv): **256 inspected**, **129 DEMO_UNUSED**, **6 USED**, **2 SHARED**, **119 UNCERTAIN**.
- **129 berkas demo TailAdmin dihapus secara individual** setelah pemeriksaan route, Blade includes/components, controller, class autoload, Vite entry/import, JS/CSS, dan referensi gambar. Seluruh 129 path sudah tidak ada. `php artisan view:cache`, optimized Composer autoload, dan build berhasil setelahnya.
- Gambar TailAdmin `public/images/*` yang tidak punya referensi statis aktif tetap dipertahankan sebagai `UNCERTAIN` karena path konten/profil/media bisa berasal dari database. `public/images/user/owner.jpg` dipakai langsung oleh fallback profil. `public/favicon.ico` dipertahankan sebagai fallback browser.
- `.env`, upload pengguna, migration existing, database existing, CSS aktif, FullCalendar, Prism, Livewire, dan aset Besofton tetap ada. `public/robots.txt` statis dihapus secara terpisah agar route robots dinamis efektif.
- Satu file HTML 25 byte yang dibuat oleh tes upload yang gagal sebelum perbaikan dipindahkan, bukan dihapus, dari `public/uploads/2026/09/5SJDyBXIzx0Ok1cYkyJGgEKiJutZcV6ZbFc7VU4X.jpg` ke `storage/app/quarantine/` setelah isi persisnya dipastikan. Upload lain tidak disentuh.
- Pencarian ulang sumber aktif tidak menemukan nama route demo, Blade component demo, atau impor JS demo yang tertinggal. `git diff --check` bersih.

## Functional audit

| Area | Status | Evidence / batas verifikasi |
|---|---|---|
| Login CMS dan dashboard | PASS | Seed admin berhasil login lewat HTTP lokal; dashboard `/admin` HTTP 200 dengan metrik database nyata. |
| Menu CMS | PASS | 20 halaman utama CMS dibuka lewat HTTP lokal dengan sesi admin, semuanya 200. Route demo 0; 175 route total; 0 route admin selain login tanpa `EnsureCmsAccess`. |
| Artikel | PASS | Tes membuat draft dengan kategori, tag, topik, penulis, gambar/alt, code, YouTube, direct answer, key takeaways, SEO/OG; edit mengisi ulang; referensi disimpan; preview privat; publish menghasilkan HTML SSR. |
| Kategori, tag, topik, penulis, video, kuis, jalur belajar, kelas | PASS | Tes create/edit/delete dan restore untuk model soft delete; create/edit berbagi form route halaman. |
| Media | FIXED | Upload berkas berbahaya dan HTML yang menyamar sebagai JPG ditolak; media yang masih dipakai artikel mendapat pesan error tanpa terhapus. Upload JPG valid tercakup tes lama. |
| Q&A | PASS | Pertanyaan pending tersembunyi sampai disetujui; endpoint moderasi diuji. Interaksi browser belum diuji. |
| Reader, bookmark, progress | PASS | Registrasi, bookmark, riwayat, progres pelajaran dan isolasi antar reader diuji. |
| Menu dan redirect | FIXED | URL lokal ambigu/berbahaya ditolak, loop redirect ditolak, invalidasi cache menu diuji. Redirect slug artikel 301 tercakup tes. |
| Kalender editorial | PARTIAL | Halaman HTTP 200, data event berasal dari post; rendering dan perpindahan bulan di browser belum diuji. |  
| Analitik | PASS | Metrik membaca tabel nyata; empty state untuk daftar artikel ada. Tidak ada nilai acak. |
| Navigasi, Tom Select, SweetAlert, toast, loading | PARTIAL | Source audit: `wire:navigate`, listener `livewire:navigated`, penghancuran Tom Select/FullCalendar, guard double submit, konfirmasi delete dan toast tunggal. Browser interaktif tidak tersedia, sehingga Back/Forward, cancel/confirm, flicker, keyboard select, dan console error belum terverifikasi. |
| Kuis | PARTIAL | Skor dihitung server, request palsu `score/is_correct/passing_score` diabaikan. Implementasi saat ini adalah single choice empat opsi; multiple choice dan true/false belum tersedia. |
| Course / learning path | PARTIAL | Urutan, progress, previous/next pelajaran dan isolasi reader diuji. Belum ada quiz lesson atau tombol continue learning khusus. |
| Artikel publik | PARTIAL | Konten utama, satu H1, meta, image, referensi, key takeaways, YouTube placeholder, dan code block tersedia di SSR. TOC/copy code/Prism memerlukan browser; artikel belum menampilkan CTA kuis dan previous/next artikel. |

## Security

- **Diperbaiki:** validasi upload kini memeriksa signature/dimensi raster, menolak nama `.php`, `.phtml`, `.phar`, dan `*.php.jpg`; berkas HTML berkedok JPG tidak lagi tersimpan.
- **Diperbaiki:** URL menu dan redirect menolak backslash/control/whitespace; redirect yang membuat loop ditolak.
- **Diperbaiki:** fallback sanitizer saat ekstensi DOM tidak tersedia sekarang fail closed dengan teks yang di-escape. Tes payload `<script>`, event handler, `javascript:` dan iframe arbitrer lulus.
- **Diperbaiki:** `passing_score` dibatasi 0–100; `/admin/logout` juga memakai middleware akses CMS.
- Tes role meliputi guest, reader, author, editor, admin, superadmin pada endpoint langsung. Force delete dan bulk action tanpa perubahan parsial saat izin gagal diuji.

## SEO dan performance

- HTML artikel nyata diuji: satu `<title>`, description, satu canonical, satu H1, OG image bila tersedia, robots, dan JSON-LD `Article`/`BreadcrumbList` valid dengan data database. Author `ProfilePage` dan video `VideoObject` diuji.
- Sitemap mengecualikan draft, future/scheduled, noindex, soft deleted, admin, dan preview. `/robots.txt` dihasilkan route: staging/lokal `Disallow: /` + meta noindex; production memberi sitemap dan menghalangi admin.
- HTTP lokal `/`, `/sitemap.xml`, `/robots.txt`, `/feed.xml`, `/cari` memberi 200 dengan tipe konten sesuai.
- Public Vite entry kini **96.11 kB JS (33.85 kB gzip)**; SweetAlert, Tom Select, dan FullCalendar berada dalam dynamic chunks, bukan preload HTML publik. CSS aktif sekitar 92.53 kB.
- Featured/related card relations kini eager loaded; settings dan menu memakai cache. Migration aditif `000007` menambah indeks query post/video dan berhasil pada database lokal.

## Final verification

- `php artisan optimize:clear`: PASS.
- `composer validate`: PASS.
- `composer dump-autoload --optimize`: PASS.
- `php vendor/bin/pint --test --quiet`: PASS seluruh repository.
- `php artisan test --compact --no-ansi`: **70 passed, 335 assertions, 0 failures**.
- `npm run build`: PASS.
- `php artisan route:list`: **175 routes**; demo route 0, admin route tanpa CMS guard 0 selain login.
- `php artisan migrate:status`: semua migrasi hingga `000007` ran.

## Belum terverifikasi

Browser plugin tidak memiliki instance tersedia pada sesi ini. Uji klik/visual untuk Livewire navigation, Tom Select, SweetAlert cancel/confirm, toast, loading, Prism copy, FullCalendar, responsif, dan console error perlu dilakukan sebelum menyatakan siap produksi. Deploy cPanel dan domain produksi tidak dilakukan.
