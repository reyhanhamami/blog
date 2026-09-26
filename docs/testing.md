# Pengujian

Jalankan:

```bash
php artisan test
vendor/bin/pint
npm run build
php artisan route:list
php artisan schedule:list
```

Pest memakai SQLite in-memory melalui `phpunit.xml` dan `RefreshDatabase`; test tidak memerlukan internet. Cakupan saat ini mencakup login seed, otorisasi reader/author, CRUD artikel, revisi, sanitasi HTML, sitemap, SEO artikel, katalog kategori, kuis, jalur belajar, kelas, pengaturan, upload tidak aman, dan smoke test route CMS. Tambah test untuk setiap perubahan alur penting.