# Arsitektur

Aplikasi Laravel tunggal menyajikan web publik dan CMS. Route publik ada di `routes/web.php`; route CMS memakai middleware `auth` dan `EnsureCmsAccess`. `PostPolicy` membatasi artikel milik author dan Gate membatasi modul editorial/sistem.

`PostWriter` menangani transaksi penyimpanan artikel, relasi tag/topik/artikel terkait, riwayat revisi, dan redirect slug lama. `HtmlSanitizer` membersihkan HTML sebelum disimpan; `ContentRenderer` membuat placeholder YouTube dari ID tervalidasi. Model `Post::published()` menjadi satu sumber aturan visibilitas artikel: status terbit dan `published_at <= now()`.

Blade memberi halaman server rendered yang crawlable. `wire:navigate` membuat navigasi internal terasa cepat, sedangkan Livewire `QuizPlayer` menilai kuis tanpa reload. JS menginisialisasi Tom Select, SweetAlert2, Prism, dan FullCalendar pada `livewire:navigated`.

MySQL/MariaDB menyimpan konten, pengguna, dan interaksi. Gambar disimpan di `public/uploads/YYYY/MM` dengan nama acak, sehingga cPanel tidak memerlukan `storage:link`. Scheduler Laravel mengubah artikel terjadwal menjadi terbit dengan cron per menit. Data analitik internal berasal dari penghitung tayangan per sesi, percobaan kuis, dan suara pembaca.