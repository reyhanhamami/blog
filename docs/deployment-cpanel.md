# Deploy ke cPanel

1. Siapkan PHP 8.3+ dan MySQL/MariaDB. Buat database serta pengguna dengan izin pada database tersebut.
2. Di mesin development, jalankan `composer install --no-dev --optimize-autoloader` dan `npm ci && npm run build`. Unggah kode, folder `vendor`, dan `public/build` melalui File Manager/FTP.
3. Arahkan document root subdomain `blog.besofton.id` ke folder `public` aplikasi. Simpan `.env` di akar aplikasi, bukan di document root.
4. Isi `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://blog.besofton.id`, `APP_KEY` unik dari `php artisan key:generate` di mesin lokal, kredensial `DB_*`, `QUEUE_CONNECTION=sync`, `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`.
5. Jalankan migration dan seeder melalui Git Version Control cPanel sesuai langkah di bawah. `.cpanel.yml` menjalankan perintah server secara otomatis; akun hosting tidak harus menyediakan Terminal. Jangan membuka endpoint web untuk menjalankan migration.
6. Pastikan `storage`, `bootstrap/cache`, dan `public/uploads` dapat ditulis proses PHP. Aset media memakai folder publik langsung; tidak memerlukan `storage:link`.
7. Tambahkan cPanel Cron Job setiap menit: `* * * * * /usr/local/bin/php /home/USERNAME/PATH/artisan schedule:run >> /dev/null 2>&1`. Sesuaikan path PHP dan aplikasi dengan akun hosting.
8. Login `/admin/login` dan ganti password seed sebelum mengumumkan situs. Periksa `/sitemap.xml`, `/robots.txt`, `/feed.xml`, dan satu artikel terbit. `/robots.txt` dibuat oleh route dan memakai `APP_URL` untuk sitemap; staging/lokal dengan `APP_ENV` selain `production` menolak crawling.

## Git Version Control tanpa Terminal

1. Pastikan commit menyertakan `.cpanel.yml`, kedua file `config/public_theme*.php`, `app/Support/PublicTheme.php`, `app/Support/PublicThemeDefaults.php`, seeder, dan seluruh perubahan aplikasi terkait. Kedua file konfigurasi tema sudah tracked di repository. Sertakan `public/build` hasil build lokal untuk perubahan frontend.
2. Di **Git Version Control > Manage > Pull or Deploy**, pilih **Update from Remote**, kemudian **Deploy HEAD Commit** saat siap menerapkan commit. Perubahan lokal harus sudah di-commit dan working tree repository cPanel bersih.
3. cPanel menjalankan task Bash dalam `.cpanel.yml` menggunakan executable PHP 8.4 dan path aplikasi existing. Urutannya:

   ```text
   set -e
   config:clear
   migrate --force --seed
   optimize:clear
   config:cache
   view:cache
   ```

   `config:clear` harus mendahului migration/seeder supaya key konfigurasi baru tidak tertutup cache dari deploy sebelumnya. Cache dibuat kembali setelah migration berhasil. `set -e` menghentikan skrip ketika suatu perintah gagal. Seeder tema memakai `firstOrCreate`, sehingga nilai custom yang sudah ada tetap dipertahankan.

4. Periksa hasil deployment di cPanel. Path yang dipertahankan pada konfigurasi ini adalah `/home/zfoeasqc/public_html/blog`, dengan PHP `/opt/cpanel/ea-php84/root/usr/bin/php`. Task existing mengasumsikan source terbaru tersedia pada path aplikasi tersebut; jika checkout repository cPanel berada di direktori terpisah, proses penyalinan source harus sudah dikonfigurasi sesuai hosting. Perubahan ini tidak menambahkan penyalinan atau mengubah path hosting.

Alur ini didukung oleh [panduan deployment Git cPanel](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/). Tidak membutuhkan endpoint maintenance publik atau Terminal cPanel. Jangan unggah `.env` development, secret, atau `bootstrap/cache/config.php` lokal ke repository/production. File cache harus dibuat oleh task deployment menggunakan environment hosting.

Jika tema jatuh ke fallback Classic, periksa log `Invalid public theme configuration` dan pastikan commit serta source aplikasi berisi file konfigurasi/default yang lengkap. Lihat [catatan perbaikan konfigurasi tema](public-theme-configuration-fix.md). Mengedit warna dari CMS tetap tidak membutuhkan perintah cache/build manual.

Untuk error Livewire update, lihat [audit transport HTTP 405](livewire-transport.md). Pastikan `APP_DEBUG=false` pada production dan pertahankan APP_KEY existing. Update deployment tidak boleh menjalankan key:generate. `.htaccess` mengarahkan canonical redirect halaman hanya untuk GET/HEAD agar POST Livewire tidak berubah menjadi GET.

## Release Phase 10 — SEO dan performa

Lihat [audit SEO/performa](seo-performance-audit.md) dan [panduan Search Console](google-indexing-guide.md). Phase ini tidak menambah migration, dependency PHP, Redis, atau Node runtime di server. Build Vite dilakukan di Windows lalu sertakan manifest dan aset hasil build. Pertahankan aset hash versi sebelumnya selama masa transisi agar halaman/tab yang masih terbuka tidak mendapat 404 saat memuat chunk lama. Jangan unggah database, HTML snapshot, cache, atau fixture `storage/app/seo-audit` dari mesin audit.

Tidak ada deploy otomatis dalam pengerjaan Phase 10. `.cpanel.yml` existing tetap utuh; task existing masih memiliki `migrate --force --seed`. Phase ini sendiri tidak memerlukan seeding/migration. Sebelum operator memilih **Deploy HEAD Commit**, tinjau task tersebut sesuai kebijakan release hosting. Jangan jalankan DemoSeeder, `migrate:fresh`, atau mengganti APP_KEY/`.env` development pada production. Source audit tidak mengubah `.env`.

Periksa melalui File Manager bahwa hosting memakai PHP 8.4, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://blog.besofton.id`, dan config cache sesuai. Snapshot production sebelum perbaikan memuat `livewire.js` tidak diminifikasi (~554 KB). Implementasi vendor memilih versi tersebut ketika `app.debug` aktif; nilai environment/cache server belum dibaca langsung. Konfirmasi konfigurasi dan rebuild config cache memakai fasilitas cPanel existing. Source tidak menonaktifkan Livewire/Alpine yang dibutuhkan navbar, navigasi, newsletter, dan kuis.

Varian gambar memakai GD WebP jika tersedia; file asli dan URL aslinya tetap dipertahankan. Upload Media Library/logo baru mencoba membuat varian secara otomatis. Untuk gambar existing, operator dapat menjalankan **sekali** melalui cPanel Cron Jobs (tanpa SSH):

```text
/opt/cpanel/ea-php84/root/usr/bin/php /home/zfoeasqc/public_html/blog/artisan media:optimize-public >> /home/zfoeasqc/public_html/blog/storage/logs/media-optimize.log 2>&1
```

Hapus jadwal cron setelah eksekusi selesai; tidak perlu menjalankan terus-menerus. Perintah hanya membuat file terpisah di `public/uploads/optimized`, tanpa menghapus original atau mengubah database artikel. Periksa ruang disk, izin tulis, hasil log, serta dukungan GD WebP hosting. Pemrosesan melewati GIF, animated WebP, JPEG berorientasi EXIF selain normal, gambar >8 megapiksel, dan gambar yang melampaui anggaran memori. Jika GD/varian tidak tersedia, rendering memakai gambar original. Ini fallback kompatibilitas; penghematan ukuran untuk upload lama baru berlaku sesudah varian tersedia. HTML request tidak melakukan konversi gambar.

`.htaccess` menambahkan kompresi teks bila `mod_deflate` tersedia dan cache immutable hanya untuk nama aset Vite/varian WebP berversi. HTML sesi, payload Livewire, data personalized, dan original upload yang mutable tidak mendapat aturan cache publik ini. Server production menampilkan `nginx`; frontend/proxy bisa mengabaikan aturan Apache. Verifikasi `Content-Encoding` dan `Cache-Control` setelah release, atau minta penyedia hosting memasang aturan ekuivalen **khusus static assets**. Hindari cache HTML authenticated.

Setelah deploy manual, periksa endpoint dan metadata menurut panduan Search Console, gambar di mobile/desktop, navbar/filter/kuis/bookmark/newsletter, serta editor CMS. Ulangi Lighthouse minimal tiga run per URL/perangkat dengan kondisi sama; laporkan median. Hasil preview SQLite lokal tidak membuktikan target production tercapai. Rollback source/manifest bila smoke test gagal; pertahankan upload original dan data production.
