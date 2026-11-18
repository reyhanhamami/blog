# Homepage featured article slider

Implementasi lokal, 18 November 2026. Tidak ada deployment production.

## Root cause dan bukti

Bug direproduksi dengan Playwright/Chrome pada tiga artikel published di SQLite QA terpisah. Sebelum perbaikan, klik nyata pada judul menghasilkan `pointerdown` dan `click`, tetapi **tidak menghasilkan mousedown/mouseup**; URL tetap `/`. Hit testing menempatkan anchor pada posisi klik, tanpa overlay yang menghalangi.

Swiper 12.1.4 menggunakan `touchStartPreventDefault:true` secara default. Pembatalan pointerdown meniadakan compatibility mouse events. Implementasi `wire:navigate` Livewire 4 yang terpasang memulai navigasi melalui mousedown/mouseup dan mencegah klik kiri biasa. Akibatnya, navigasi tidak dimulai meskipun href benar. Mengubah z-index atau menonaktifkan seluruh click protection tidak menyelesaikan interaksi ini dengan benar.

Perbaikan memakai `touchStartPreventDefault:false`, tetap mempertahankan `preventClicks:true`, `preventClicksPropagation:true`, dan threshold drag 10 px. Guard **capture mouseup** memeriksa `swiper.allowClick` agar Livewire tidak menavigasi ketika gesture benar-benar drag, sebelum event click Swiper berlangsung. Semua judul dan CTA tetap anchor dengan href dan wire:navigate; tidak ada window.location workaround.

Sesudah perbaikan, trace klik yang sama mencakup pointerdown → mousedown → mouseup → click, dan URL berubah ke slug artikel yang benar. Browser test juga memverifikasi drag yang dimulai dari judul tidak membuka artikel, sementara Ctrl+Click, klik tengah, dan Enter tetap bekerja. Mekanisme diperiksa pada source package yang terpasang; [dokumentasi Swiper](https://swiperjs.com/swiper-api) dan [Livewire Navigate](https://livewire.laravel.com/docs/4.x/navigate) menjadi rujukan API umum.

## Konfigurasi dan lifecycle

- Modul resmi **Navigation + Autoplay**, delay **5000 ms**, speed **600 ms**, slidesPerView 1, rewind true, loop false. Tidak ada clone slide atau interval custom.
- Instance hanya dibuat untuk >1 slide. Satu artikel tidak memuat entry Swiper. HomepageContentService tetap memilih hingga tiga featured articles sesuai kebijakan existing.
- Autoplay memakai disableOnInteraction false, pauseOnMouseEnter true, stopOnLastSlide false. State controller menghormati gabungan hover, fokus di dalam region, pointer yang masih ditekan, document.hidden, pause pengguna, dan prefers-reduced-motion. Resume dari satu event tidak boleh membatalkan alasan pause lain.
- Reduced motion mematikan autoplay, menyembunyikan kontrol autoplay, dan menggunakan speed 0 untuk navigasi manual. Perubahan preference saat runtime juga didengar.
- Pause/resume memakai tombol kecil di samping panah existing, label aksesibel, dan state aria-pressed. Resume menunggu hover/fokus meninggalkan region, sehingga fokus keyboard tidak digeser oleh autoplay.
- Slide tidak aktif menjadi inert/aria-hidden di browser agar keyboard tidak masuk ke anchor yang tidak terlihat. Server HTML tetap memuat anchor tiap artikel.
- Mount berjalan saat initial load maupun setelah livewire:navigated, termasuk entry Vite yang baru pertama dimuat saat masuk homepage melalui SPA. Mount berulang pada elemen yang sama memakai instance existing.
- livewire:navigating membatalkan listener lewat AbortController, memulihkan atribut slide, dan memanggil destroy Swiper; timer native Autoplay ikut dihentikan. Microtask pending mengabaikan instance yang sudah disposed.

Core CSS Swiper dimuat bersama entry homepage agar transform/height memiliki transition yang benar dan touch-action mendukung scroll vertikal/swipe horizontal. Desain media, warna, ukuran font, dan grid tetap existing. Gambar pertama eager; gambar berikutnya lazy. CSS tambahan hanya menjaga tombol hidden dan menyediakan ruang bawah untuk kontrol pada slider multi-artikel.

## File yang berubah untuk slider

| File | Perubahan |
| --- | --- |
| `resources/js/public-home.js` | Perbaikan event, Autoplay, pause state, accessibility, lifecycle |
| `resources/views/public/home.blade.php` | Region/slide labels, flag gambar pertama, pause/resume button |
| `resources/views/public/partials/home-featured.blade.php` | Eager hanya untuk gambar pertama; href tetap memakai post tiap slide |
| `resources/css/public.css` | Hidden control dan ruang bawah agar kontrol tidak menutupi teks |
| `tests/Feature/FeaturedSliderTest.php` | Regresi server HTML untuk satu/dua/tiga artikel, href, conditional assets dan image loading |
| `tests/browser/seed-featured-slider.php` | Fixture guarded SQLite QA, tanpa data production/DemoSeeder |
| `tests/browser/featured-slider.mjs` | Klik nyata, autoplay, responsive, keyboard, drag, lifecycle |
| `tests/browser/featured-slider-touch.mjs` | Native touch input dan visibility/manual resume checks |
| `public/build/manifest.json` dan aset hasil build | Entry JS/CSS terbaru |

Audit membaca `public.js` dan layout tanpa mengubah perilakunya untuk slider. Pemeriksaan Pint awal juga menemukan tujuh file dari fase sebelumnya yang belum sesuai style. Formatter hanya merapikan import, spasi/braces dan fully qualified names pada `MediaController`, `SettingsController`, `PublicContentController`, `AppServiceProvider`, `PublicImage`, `bootstrap/app.php`, dan `SeoReadinessTest`; tidak menambah perubahan fungsional di luar slider.

## Hasil pengujian

| Pemeriksaan | Hasil |
| --- | --- |
| Satu artikel | PASS — tanpa instance/autoplay entry, title klik membuka detail |
| Dua artikel | PASS — SPA entry dari artikel, Previous rewind ke slide kedua, CTA kedua membuka slug kedua |
| Tiga artikel | PASS — seluruh judul dan CTA memakai slug masing-masing |
| Viewport 1440 / 1024 / 768 / 390 | PASS — 24 klik nyata (3 slide × 2 anchor × 4 viewport), Next dan Back, tanpa overflow horizontal |
| Layout | PASS — gambar kiri/teks kanan pada desktop/tablet, bertumpuk pada mobile; screenshot dan bounding boxes diperiksa |
| Autoplay | PASS — perpindahan diamati sekitar 5 detik, kemudian judul slide aktif diklik; last→first dan CTA juga lulus |
| Hover, fokus, pause/resume | PASS — tidak bergeser selama interval >5 detik ketika pause; dapat melanjutkan sesudah pause dilepas |
| Keyboard/native link | PASS — Enter, Ctrl+Click, klik tengah; native tab membuka URL benar |
| Swipe/drag | PASS — mouse drag mulai dari judul tidak menavigasi; native CDP touch swipe pada 390 px lalu tap CTA kedua membuka artikel kedua |
| Reduced motion | PASS — tidak autoplay, manual Next dan judul tetap bekerja |
| Visibility | PASS pada handler hidden/visible; **native background-tab visibility NOT VERIFIED**: Chrome headless tetap melaporkan semua tab visible, sehingga test memakai visibilitychange dengan state terkontrol dan mencatat nativeVisibility:false |
| Manual navigation | PASS — autoplay berlanjut setelah interaksi manual selesai dan fokus meninggalkan region |
| Livewire lifecycle | PASS — entry pertama melalui SPA, instance tidak ganda pada event berulang, instance lama destroyed, homepage→artikel→Back/Forward berulang |
| Console | PASS — tidak ada page/console JavaScript error pada suite utama; touch suite tanpa page error |
| PHP | PASS — `php artisan test --compact --no-ansi`: **263 tests, 2.750 assertions** |
| Pint | PASS — `php vendor/bin/pint --test --quiet`, exit 0 |
| JavaScript existing | PASS — **14 tests**, Node native runner |
| Vite | PASS — `npm run build`, manifest merujuk file yang tersedia |

Tests dijalankan dengan Windows native PHP 8.3.0, Node 24, Playwright existing dan Google Chrome headless existing. Database QA bernama `featured-slider-qa.sqlite`; script menolak database lain. Font eksternal dikosongkan di suite utama untuk mengurangi variasi jaringan; pemeriksaan fokus pada interaksi/layout slider, bukan PageSpeed atau perubahan typography. Screenshot/log/hasil JSON lokal berada di `storage/app/featured-slider-qa` yang diabaikan Git.

## Menjalankan ulang dan deployment

Gunakan environment lokal (tanpa mengubah .env): APP_ENV=local, APP_DEBUG=false, APP_URL=http://127.0.0.1:8768, DB_CONNECTION=sqlite, DB_DATABASE=absolute path `storage/app/featured-slider-qa/featured-slider-qa.sqlite`, SESSION_DRIVER=file, CACHE_STORE=array. Buat SQLite kosong bila belum ada lalu jalankan `php tests/browser/seed-featured-slider.php 3`. Preview router lokal yang mengabaikan hot file perlu memakai build Vite, atau gunakan konfigurasi server QA existing. Jangan gunakan environment/database production.

Set PLAYWRIGHT_MODULE ke absolute path instalasi existing `playwright-core/index.mjs`, SLIDER_QA_CHROMIUM ke Chrome/Chromium existing, dan SLIDER_QA_URL ke origin preview. Jalankan `node tests/browser/featured-slider.mjs` lalu `node tests/browser/featured-slider-touch.mjs`.

Build final memuat `assets/public-home-B44D_USV.js` dan `assets/public-home-C2J0D0kV.css`; gunakan manifest hasil build yang sama saat release Git cPanel. Pertahankan aset hash lama selama masa transisi untuk tab yang masih terbuka. Tidak ada migration baru, dependency baru, Redis, perubahan .env/.cpanel.yml, atau Node runtime production. Jangan unggah SQLite/fixture/log QA ke hosting. Deployment dan smoke test production dilakukan manual; perubahan ini belum diverifikasi pada production.
