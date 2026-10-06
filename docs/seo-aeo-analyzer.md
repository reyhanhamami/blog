# Besofton SEO & AEO Analyzer

Panel di form Create/Edit memberi panduan SEO, keterbacaan, dan AEO dari state form saat ini. Analisis berjalan di browser tanpa API, subscription, atau penyimpanan skor. Hanya `focus_keyphrase` yang ditambahkan ke data artikel. Saran tidak memblokir penyimpanan dan bukan jaminan peringkat pencarian.

## Alur

`article-editor.js` menyinkronkan HTML editor ke field `content` dan memancarkan event input. `seo-analyzer-ui.js` membaca field form, menjalankan `analyzeArticle()` dari `seo-analyzer.js`, lalu menampilkan status dan pesan sebagai teks. Analisis konten didebounce 320 ms; hasil parsing HTML dipakai ulang sampai konten berubah. Create dan Edit memakai Blade form dan modul yang sama. Saat `wire:navigate`, listener lama dibersihkan oleh `destroy()` dari form.

Fallback mengikuti halaman publik: judul SEO memakai judul artikel dan akhiran nama situs; deskripsi memakai ringkasan, teks artikel, lalu judul; canonical memakai URL slug; OG image memakai gambar unggulan. Referensi merupakan jumlah URL valid yang sudah tersimpan pada halaman referensi dan diperbarui setelah reload form. Tanggal terbit belum ada pada draf.

## Aturan

| ID | Grup | Kondisi utama | Status ketika perlu tindakan |
| --- | --- | --- | --- |
| `seo.title`, `seo.title_length` | SEO | Judul tidak ada; judul hasil publik di luar kisaran 30–60 karakter | Masalah; Perlu diperbaiki |
| `seo.description`, `seo.description_length` | SEO | Fallback deskripsi atau panjang di luar kisaran 120–160 | Perlu diperbaiki |
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
| `aeo.author`, `aeo.canonical`, `aeo.schema`, `aeo.updated` | AEO | Penulis/bio kosong, slug belum ada, data inti schema belum lengkap; tanggal aktual bila tersimpan | Masalah / Perlu diperbaiki |

Pemeriksaan keyphrase dimatikan ketika `noindex` aktif. Jika focus keyphrase kosong, pemeriksaan keyphrase tidak ditampilkan. Angka panjang, kepadatan, dan durasi baca adalah panduan kasar; tidak ada penilaian kualitas fakta, search volume, prediksi ranking, backlink, atau crawl SERP. Pemecahan kalimat Bahasa Indonesia bersifat heuristik. Gambar yang bersifat dekoratif dapat sengaja memiliki alt kosong, sehingga peringatan alt tetap perlu ditinjau penulis.

## Menambah aturan

Tambahkan evaluasi di `analyzeArticle()` dengan `add(id, group, status, title, message, target, priority)`. Gunakan ID stabil, pesan yang menjelaskan tindakan, dan `target` berupa nama field form bila hasil perlu bisa diklik. Semua output ditampilkan melalui `textContent`; jangan merender HTML dari artikel atau pesan aturan.
