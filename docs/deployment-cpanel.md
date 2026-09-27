# Deploy ke cPanel

1. Siapkan PHP 8.3+ dan MySQL/MariaDB. Buat database serta pengguna dengan izin pada database tersebut.
2. Di mesin development, jalankan `composer install --no-dev --optimize-autoloader` dan `npm ci && npm run build`. Unggah kode, folder `vendor`, dan `public/build` melalui File Manager/FTP.
3. Arahkan document root subdomain `blog.besofton.id` ke folder `public` aplikasi. Simpan `.env` di akar aplikasi, bukan di document root.
4. Isi `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://blog.besofton.id`, `APP_KEY` unik dari `php artisan key:generate` di mesin lokal, kredensial `DB_*`, `QUEUE_CONNECTION=sync`, `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`.
5. Jalankan migration dan seeder pada database target melalui terminal cPanel bila ada, atau pada salinan staging dengan kredensial target sebelum unggah. Jalankan `php artisan migrate --force` dan `php artisan db:seed --force`. Jangan membuka endpoint web untuk menjalankan migration.
6. Pastikan `storage`, `bootstrap/cache`, dan `public/uploads` dapat ditulis proses PHP. Aset media memakai folder publik langsung; tidak memerlukan `storage:link`.
7. Tambahkan cPanel Cron Job setiap menit: `* * * * * /usr/local/bin/php /home/USERNAME/PATH/artisan schedule:run >> /dev/null 2>&1`. Sesuaikan path PHP dan aplikasi dengan akun hosting.
8. Login `/admin/login` dan ganti password seed sebelum mengumumkan situs. Periksa `/sitemap.xml`, `/robots.txt`, `/feed.xml`, dan satu artikel terbit. `/robots.txt` dibuat oleh route dan memakai `APP_URL` untuk sitemap; staging/lokal dengan `APP_ENV` selain `production` menolak crawling.

Saat memperbarui aplikasi, unggah kode dan build baru, jalankan migration bila ada, lalu `php artisan optimize:clear` jika terminal tersedia. Jangan unggah `.env` development atau secret ke repository.