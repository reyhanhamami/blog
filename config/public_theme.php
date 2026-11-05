<?php

// Besofton Classic: values audited from resources/css/public.css.
return [
    'groups' => [
        'Warna Utama' => [
            'primary' => ['Primary Color', '#e8af4b', 'Badge, indikator progres, dan elemen interaktif.'],
            'primary-hover' => ['Primary Hover', '#f3c36d', 'Hover tombol dan elemen interaktif.'],
            'secondary' => ['Secondary Color', '#0b0b0b', 'Tombol sekunder, chip aktif, dan panel gelap.'],
            'on-secondary' => ['Teks Secondary', '#ffffff', 'Teks pada tombol sekunder dan panel gelap.'],
            'accent' => ['Accent Color', '#e8af4b', 'Highlight INSIGHTS, garis dekoratif, dan aksen.'],
        ],
        'Background & Cards' => [
            'background' => ['Website Background', '#f7f3eb', 'Latar halaman dan gradasi hero.'],
            'surface' => ['Card Background', '#fffdf9', 'Kartu artikel, form, dan input.'],
            'surface-alt' => ['Alternative Surface', '#f9f7f3', 'Panel alternatif, callout, dan hover permukaan.'],
            'border' => ['Border Color', '#ddd6c8', 'Garis pemisah dan batas kartu.'],
            'input-border' => ['Input Border', '#a99f91', 'Batas input dan kontrol filter.'],
            'focus' => ['Focus Indicator', '#986215', 'Indikator fokus keyboard, checkbox, dan pilihan kuis.'],
        ],
        'Typography' => [
            'heading' => ['Heading Color', '#0a0a0a', 'Judul halaman dan judul kartu.'],
            'text' => ['Body Text Color', '#0a0a0a', 'Paragraf dan isi konten.'],
            'text-muted' => ['Muted Text Color', '#57534e', 'Ringkasan, metadata, dan keterangan.'],
            'link' => ['Link Color', '#0a0a0a', 'Tautan di isi artikel dan tautan teks.'],
            'link-hover' => ['Link Hover', '#8a5600', 'Hover tautan dan navigasi.'],
            'eyebrow' => ['Eyebrow Color', '#825006', 'Label bagian dan keterangan kecil berwarna emas gelap.'],
        ],
        'Header' => [
            'header-bg' => ['Header Background', '#fbf9f5', 'Latar navigasi desktop dan mobile.'],
            'header-text' => ['Header Navigation Text', '#0a0a0a', 'Teks navigasi dan menu akun.'],
        ],
        'Footer & Newsletter' => [
            'footer-bg' => ['Footer Background', '#faf8f4', 'Latar navigasi footer.'],
            'footer-text' => ['Footer Text', '#0a0a0a', 'Teks footer; keterangan mewarisi variasi warna ini.'],
            'footer-cta-bg' => ['Newsletter CTA Background', '#11100e', 'Latar CTA newsletter, terpisah dari footer.'],
            'footer-cta-text' => ['Newsletter CTA Text', '#ffffff', 'Judul dan isi newsletter. Input mewarisi Card Background dan Body Text.'],
            'footer-cta-accent' => ['Newsletter Accent', '#e8af4b', 'Highlight newsletter; terpisah dari aksen halaman agar terbaca pada CTA gelap.'],
        ],
        'Buttons' => [
            'button-bg' => ['Primary Button Background', '#e8af4b', 'Tombol emas, tombol pencarian, dan kirim newsletter.'],
            'button-text' => ['Primary Button Text', '#0a0a0a', 'Teks tombol utama dan badge. Hover mewarisi Primary Hover.'],
        ],
    ],
];
