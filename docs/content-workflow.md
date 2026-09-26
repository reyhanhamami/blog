# Alur konten

1. Author membuat draft di `/admin/posts/create` dan hanya dapat mengubah artikelnya sendiri.
2. Author mengirim `pending_review`; editor, admin, atau superadmin dapat meninjau, menjadwalkan, menerbitkan, dan mengarsipkan.
3. Editor mengisi penulis, kategori, tag, topik, SEO, gambar, dan artikel terkait sebelum terbit.
4. Artikel terjadwal diterbitkan oleh Laravel Scheduler. Cron harus aktif tiap menit.
5. Setiap penyimpanan membuat revision; restore revision membuat versi baru. Penghapusan biasa memindahkan artikel ke Trash, dengan pemulihan dan penghapusan permanen oleh role yang berwenang.
6. Pertanyaan pembaca selalu masuk status `pending`; editor menyetujui atau menolak dan dapat menjawab. Hanya pertanyaan approved yang tampil publik.

Editor artikel memiliki toolbar, draf lokal browser, peringatan perubahan belum disimpan, code block, dan shortcode YouTube. Simpan berkala melalui tombol Simpan; draf lokal bukan pengganti penyimpanan CMS.