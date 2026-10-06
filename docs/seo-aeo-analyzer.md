# Besofton SEO & AEO Analyzer

Panel di form Create/Edit memberi panduan SEO, keterbacaan, dan AEO dari state form saat ini. Analisis berjalan di browser tanpa API, subscription, atau penyimpanan skor. Saran tidak memblokir penyimpanan dan bukan jaminan peringkat pencarian. Semua indikator tidak harus hijau; penulis tetap menilai relevansi setiap saran.

## Alur

`article-editor.js` menyinkronkan HTML editor ke field `content` dan memancarkan event input. `seo-analyzer-ui.js` membaca field form, menjalankan `analyzeArticle()` dari `seo-analyzer.js`, lalu menampilkan status dan pesan sebagai teks. Analisis konten didebounce 320 ms; hasil parsing HTML dipakai ulang sampai konten berubah. Create dan Edit memakai Blade form dan modul yang sama. Saat `wire:navigate`, listener lama dibersihkan oleh `destroy()` dari form.

Fallback mengikuti `resources/views/public/post.blade.php`: judul SEO memakai `seo_title`, lalu judul artikel, dan akhiran ` | Besofton Insights`; deskripsi SEO dan Article JSON-LD memakai `seo_description`, lalu `excerpt`, lalu `Str::limit(content_plain ?: strip_tags(content) ?: title, 155)`. Field `summary` tampil pada badan artikel, tetapi bukan fallback metadata. Canonical memakai URL slug. OG image memakai `og_image`, lalu `featured_image`; renderer tidak memiliki gambar OG bawaan. Article JSON-LD memakai judul artikel sebagai `headline`, nama profil penulis atau `Tim Besofton`, `published_at`, `updated_at`, URL slug, dan `featured_image` hanya bila tersedia. Bio penulis dan gambar tidak wajib untuk aturan kesiapan schema saat ini.

Create/Edit memakai repeater referensi yang menyimpan relasi `post_sources` dan membaca URL valid dari isian saat ini, termasuk sebelum artikel disimpan. Referensi HTTP/HTTPS yang lengkap dengan judul dihitung sebagai sumber eksternal SEO. Tautan eksternal dalam badan artikel juga memenuhi sinyal sumber; jika tautan ada tanpa entri referensi, AEO menampilkan info. Halaman publik memakai daftar referensi yang sudah ada. Tanggal terbit draf belum ada; publikasi langsung mengisinya saat simpan, sedangkan job jadwal memindahkan `scheduled_at` ke `published_at` saat terbit. Karena itu draf dan artikel terjadwal mendapat info schema, sementara artikel published yang sudah tersimpan tanpa tanggal terbit mendapat masalah yang spesifik.

## Aturan

| ID | Grup | Kondisi utama | Status ketika perlu tindakan |
| --- | --- | --- | --- |
| `seo.title`, `seo.title_length` | SEO | Judul tidak ada; judul hasil publik di luar kisaran 30–60 karakter | Masalah; Perlu diperbaiki |
| `seo.description`, `seo.description_length` | SEO | Deskripsi resolved kosong atau panjang di luar kisaran 120–160; fallback yang tersedia dihitung baik | Masalah / Perlu diperbaiki |
| `seo.slug` | SEO | Slug kosong, terlalu panjang (>75), atau tanda hubung berulang | Masalah / Perlu diperbaiki |
| `seo.title_phrase`, `seo.slug_phrase`, `seo.description_phrase`, `seo.intro_phrase`, `seo.heading_phrase` | SEO | Focus keyphrase tidak ditemukan setelah normalisasi huruf dan tanda baca | Perlu diperbaiki |
| `seo.density` | SEO | Keyphrase tidak ada, di bawah 0,5%, atau di atas 2,5% dari jumlah kata | Masalah / Perlu diperbaiki |
| `seo.internal_links`, `seo.external_links` | SEO | Tidak ada tautan internal; tidak ada tautan eksternal maupun referensi | Perlu diperbaiki |
| `seo.image_alt`, `seo.images`, `seo.featured_image`, `seo.og_image` | SEO | Alt kosong, artikel panjang tanpa gambar, gambar unggulan/OG kosong | Masalah / Perlu diperbaiki |
| `seo.category`, `seo.topic` | SEO | Kategori atau topik belum dipilih | Perlu diperbaiki |
| `readability.content`, `readability.length` | Readability | Konten kosong atau artikel panjang umum di bawah 300 kata | Masalah / Perlu diperbaiki |
| `readability.body_h1`, `readability.headings` | Readability | H1 di isi, H2 tidak ada, atau lompatan tingkat heading | Perlu diperbaiki |
| `readability.paragraphs`, `readability.sentences` | Readability | Paragraf >120 kata; >30% kalimat berisi >30 kata | Perlu diperbaiki |
| `readability.subheadings`, `readability.lists` | Readability | Artikel >600 kata memiliki bagian >300 kata tanpa H2/H3; tutorial panjang tanpa daftar | Perlu diperbaiki |
| `aeo.direct_answer`, `aeo.takeaways`, `aeo.references` | AEO | Jawaban kosong atau >80 kata; poin inti kosong pada artikel panjang; referensi faktual kosong | Perlu diperbaiki |
| `aeo.author`, `aeo.canonical`, `aeo.schema`, `aeo.updated` | AEO | Profil penulis opsional untuk atribusi; canonical otomatis; schema memakai data resolved dan status publikasi; tanggal aktual bila tersimpan | Info / Masalah / Perlu diperbaiki |

Pemeriksaan keyphrase dimatikan ketika `noindex` aktif. Jika focus keyphrase kosong, pemeriksaan keyphrase tidak ditampilkan dan panel memberi satu CTA netral. Kata kunci yang tidak ada pada isi artikel tetap masalah. Kalimat dibaca per paragraf dan item daftar; heading, caption, tabel, dan kode tidak dipakai sebagai pembuka atau bahan panjang kalimat. Angka panjang, kepadatan, dan durasi baca adalah panduan kasar; tidak ada penilaian kualitas fakta, search volume, prediksi ranking, backlink, atau crawl SERP. Pemecahan kalimat Bahasa Indonesia bersifat heuristik. Gambar yang bersifat dekoratif dapat sengaja memiliki alt kosong, sehingga peringatan alt tetap perlu ditinjau penulis.

## Menambah aturan

Tambahkan evaluasi di `analyzeArticle()` dengan `add(id, group, status, title, message, target, priority)`. Gunakan ID stabil, pesan yang menjelaskan tindakan, dan `target` berupa nama field form bila hasil perlu bisa diklik. Semua output ditampilkan melalui `textContent`; jangan merender HTML dari artikel atau pesan aturan.
