<?php

return [
    'groups' => [
        'Dasbor' => [
            'cms.access' => 'Masuk ke CMS', 'dashboard.view' => 'Lihat dasbor',
        ],
        'Konten' => [
            'articles.view' => 'Lihat artikel', 'articles.create' => 'Buat artikel', 'articles.update' => 'Ubah semua artikel',
            'articles.update-own' => 'Ubah artikel sendiri', 'articles.review' => 'Tinjau artikel', 'articles.publish' => 'Terbitkan artikel',
            'articles.delete' => 'Hapus semua artikel', 'articles.delete-own' => 'Hapus artikel sendiri',
            'articles.restore' => 'Pulihkan artikel', 'articles.force-delete' => 'Hapus artikel permanen',
            'categories.view' => 'Lihat kategori', 'categories.create' => 'Buat kategori', 'categories.update' => 'Ubah kategori', 'categories.delete' => 'Hapus kategori',
            'tags.view' => 'Lihat tag', 'tags.create' => 'Buat tag', 'tags.update' => 'Ubah tag', 'tags.delete' => 'Hapus tag',
            'topics.view' => 'Lihat topik', 'topics.create' => 'Buat topik', 'topics.update' => 'Ubah topik', 'topics.delete' => 'Hapus topik',
            'authors.view' => 'Lihat penulis', 'authors.manage' => 'Kelola penulis',
            'pages.view' => 'Lihat halaman', 'pages.manage' => 'Kelola halaman',
        ],
        'Media' => [
            'media.view' => 'Lihat media', 'media.upload' => 'Unggah dan ubah media', 'media.delete' => 'Hapus media',
            'videos.view' => 'Lihat video', 'videos.create' => 'Buat video', 'videos.update' => 'Ubah video', 'videos.delete' => 'Hapus video',
        ],
        'Pembelajaran' => [
            'quizzes.view' => 'Lihat kuis', 'quizzes.create' => 'Buat kuis', 'quizzes.update' => 'Ubah kuis', 'quizzes.delete' => 'Hapus kuis',
            'learning-paths.view' => 'Lihat jalur belajar', 'learning-paths.create' => 'Buat jalur belajar', 'learning-paths.update' => 'Ubah jalur belajar', 'learning-paths.delete' => 'Hapus jalur belajar',
            'courses.view' => 'Lihat kelas', 'courses.create' => 'Buat kelas', 'courses.update' => 'Ubah kelas', 'courses.delete' => 'Hapus kelas',
        ],
        'Interaksi' => [
            'qa.view' => 'Lihat tanya jawab', 'qa.moderate' => 'Moderasi tanya jawab',
        ],
        'Publikasi & SEO' => [
            'editorial-calendar.view' => 'Lihat kalender editorial',
            'analytics.view' => 'Lihat analitik',
            'redirects.view' => 'Lihat redirect', 'redirects.manage' => 'Kelola redirect',
        ],
        'Manajemen' => [
            'users.view' => 'Lihat pengguna', 'users.create' => 'Buat pengguna', 'users.update' => 'Ubah pengguna', 'users.delete' => 'Hapus pengguna',
            'roles.view' => 'Lihat role dan izin', 'roles.manage' => 'Kelola role dan izin',
            'menus.view' => 'Lihat menu', 'menus.manage' => 'Kelola menu',
            'settings.view' => 'Lihat pengaturan', 'settings.manage' => 'Kelola pengaturan',
        ],
    ],
    'defaults' => [
        'superadmin' => [], // Gate::before grants every permission.
        'admin' => ['cms.access', 'dashboard.view', 'articles.*', 'categories.*', 'tags.*', 'topics.*', 'authors.*', 'pages.*', 'media.*', 'videos.*', 'quizzes.*', 'learning-paths.*', 'courses.*', 'qa.*', 'editorial-calendar.view', 'analytics.view', 'redirects.*', 'users.*', 'roles.view', 'menus.*', 'settings.*'],
        'editor' => ['cms.access', 'dashboard.view', 'articles.view', 'articles.create', 'articles.update', 'articles.review', 'articles.publish', 'articles.delete', 'articles.restore', 'categories.*', 'tags.*', 'topics.*', 'authors.*', 'pages.*', 'media.*', 'videos.*', 'quizzes.*', 'learning-paths.*', 'courses.*', 'editorial-calendar.view', 'analytics.view', 'qa.*'],
        'author' => ['cms.access', 'dashboard.view', 'articles.view', 'articles.create', 'articles.update-own', 'articles.delete-own', 'media.view', 'media.upload'],
        'reader' => [],
    ],
    'descriptions' => [
        'cms.access' => 'Melepas akses CMS juga melepas izin modul lain.',
        'articles.publish' => 'Membuat artikel terlihat oleh publik.',
        'articles.force-delete' => 'Menghapus artikel secara permanen dan tidak dapat dipulihkan.',
        'users.delete' => 'Menghapus akun pengguna.',
        'roles.manage' => 'Mengubah akses semua pengguna melalui role.',
        'settings.manage' => 'Mengubah konfigurasi situs.',
    ],
    'dependencies' => [
        'cms.access' => ['dashboard.view'],
        'dashboard.view' => ['cms.access'],
        'roles.manage' => ['roles.view', 'cms.access'],
    ],
];
