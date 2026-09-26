# SEO dan AEO

Artikel publik hanya muncul jika `status=published`, `published_at` tidak di masa depan, dan tidak berada di Trash. Draft anonim mendapat 404. Preview CMS memakai otorisasi dan `noindex,nofollow`.

Halaman artikel menghasilkan title, meta description, canonical, Open Graph dasar, dan JSON-LD `Article` dengan penulis serta tanggal. Sitemap mengabaikan draft dan `noindex`; RSS memuat artikel terbit terbaru. Arsip dan halaman statis server rendered. Slug terbit yang berubah diarahkan HTTP 301 melalui tabel `redirects`.

Gunakan judul yang jelas, ringkasan asli, alt text gambar, penulis yang benar, dan tautan internal relevan. Tag/topik membuat cluster konten; artikel terkait manual didahulukan, lalu fallback kategori. Kuis, video, dan jalur belajar membantu navigasi lanjut. Fondasi ini tidak menjamin peringkat Google atau tampilan AI Search.