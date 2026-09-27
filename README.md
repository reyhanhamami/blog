# Besofton Insights

Satu aplikasi Laravel 13 untuk blog publik, CMS, dan akun pembaca. Frontend memakai Blade, Livewire 4, Tailwind CSS 4, dan Vite 7. Target produksi: `https://blog.besofton.id` pada PHP 8.3+ dan MySQL/MariaDB.

## Kebutuhan

- PHP 8.3+ dengan ekstensi umum Laravel (`pdo_mysql`, `mbstring`, `dom`, `fileinfo`, `gd` untuk uji upload).
- Composer 2, Node.js dan npm untuk build di mesin development.
- MySQL/MariaDB produksi atau SQLite untuk test.

## Instalasi development

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Atur `DB_*` di `.env`, lalu:

```bash
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

CMS: `/admin/login`. Akun seed awal: **admin@gmail.com / asddsa123**, role `superadmin`. Ganti password sebelum produksi. Seeder hanya membuat akun jika belum ada, sehingga menjalankannya lagi tidak mengembalikan password yang sudah diganti.

### Data contoh development

`php artisan db:seed` hanya menjalankan data inti: role, permission, akun admin, pengaturan dasar, halaman informasi, dan menu yang sudah diperlukan situs. Artikel dan akun contoh **tidak** ditambahkan otomatis, termasuk di production.

Untuk mengisi dataset kecil yang saling terhubung secara eksplisit:

```bash
php artisan db:seed --class=DemoSeeder
```

Seeder demo aman dijalankan ulang. Record yang cocok dengan email atau slug contoh tidak ditimpa, sehingga perubahan manual tetap tersimpan. Dataset mencakup dua artikel, satu video edukasi [OpenAI dan DeepLearning.AI](https://www.youtube.com/watch?v=H4YK_7MAckk), satu kuis dengan tiga tipe pertanyaan, satu learning path, satu course dengan progres reader yang belum selesai, Q&A, feedback, bookmark, subscriber, dan redirect. Media logo hanya diregistrasi bila file `storage/app/public/logo.png` dan URL publiknya benar-benar ada; seeder tidak membuat gambar palsu. Analitik contoh hanya berisi 3 dan 1 tayangan pada dua artikel, karena aplikasi menyimpan penghitung tayangan pada artikel dan belum memiliki tabel search log.

Akun demo (password awal masing-masing `asddsa123`): `editor@besofton.id` (`editor`), `author@besofton.id` (`author`), dan `reader@besofton.id` (`reader`). Password yang sudah diganti tidak direset oleh seeder. Semua akun ini hanya untuk development; ganti password atau hapus akun demo sebelum menggunakan database sebagai data production.

## Pengembangan dan test

```bash
npm run dev
php artisan test
vendor/bin/pint
npm run build
php artisan route:list
```

`QUEUE_CONNECTION=sync` cukup untuk fitur utama. Scheduler menerbitkan artikel terjadwal; jalankan `php artisan schedule:run` setiap menit di cron. Lihat [panduan cPanel](docs/deployment-cpanel.md).

## Modul

CMS menyediakan artikel dengan status editorial, aksi massal, revisi, Trash, preview privat, SEO, kategori, tag, topik, penulis, gambar, video YouTube, kuis, jalur belajar, kelas, tanya jawab, halaman statis, menu, pengguna, pengaturan, redirect, kalender editorial, dan analitik dasar. Blog publik menyediakan artikel, arsip, pencarian, RSS, sitemap, akun pembaca, bookmark, riwayat baca, dan progres pelajaran.

Input angka CMS memakai mask reusable (`integer` sebagai default, serta `quantity`, `decimal`, `duration`, `percentage`, `currency`, `money`, dan `price` lewat atribut `data-numeric-mask`). Pemisah ribuan dan simbol mata uang hanya tampil di form; nilai yang dikirim ke server tetap numerik.

Editor artikel menyimpan HTML yang dibersihkan melalui allowlist. Embed YouTube menggunakan shortcode terkontrol `[youtube:VIDEO_ID]` dan dimuat setelah pembaca menekan tombol putar.

## Produksi

Build aset di mesin development dan unggah `public/build` bersama kode aplikasi. Arahkan document root domain ke `public`. `public/uploads` harus dapat ditulis oleh PHP. Pastikan `APP_DEBUG=false`, `APP_URL` benar, dan `APP_KEY` unik. Tidak diperlukan Node runtime, Redis, Supervisor, atau proses queue permanen.

Dokumen lain: [arsitektur](docs/architecture.md), [SEO](docs/seo.md), [alur konten](docs/content-workflow.md), [pengujian](docs/testing.md), [audit pra-produksi](docs/preproduction-qa.md).
