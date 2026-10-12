@php
    $paths = [
        'dashboard' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        'posts' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h8 M8 17h6',
        'categories' => 'M3 7V5h6l2 2h10v13H3z',
        'tags' => 'M3 3h8l10 10-8 8L3 11z M7 7h.01',
        'topics' => 'M12 3 3 8l9 5 9-5z M3 12l9 5 9-5 M3 16l9 5 9-5',
        'authors' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M17 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-4',
        'media' => 'M3 3h18v18H3z M3 17l6-6 4 4 3-3 5 5 M16 7h.01',
        'videos' => 'M3 5h12v14H3z M15 10l6-4v12l-6-4',
        'pages' => 'M4 3h16v18H4z M8 7h8 M8 12h8 M8 17h5',
        'quizzes' => 'M9 3H5v18h14V3h-4 M9 2h6v4H9z M9 10h6 M9 15l2 2 4-4',
        'learning-paths' => 'M4 4h4v4H4z M16 16h4v4h-4z M8 6h9a3 3 0 0 1 0 6H7a3 3 0 0 0 0 6h9',
        'courses' => 'M12 5C8 2 4 3 2 4v16c3-2 7-2 10 0 3-2 7-2 10 0V4c-2-1-6-2-10 1z M12 5v15',
        'questions' => 'M21 3H3v14h5l4 4 4-4h5z M9 8a3 3 0 0 1 6 0c0 2-3 2-3 3 M12 14h.01',
        'editorial-calendar' => 'M3 5h18v16H3z M7 3v4 M17 3v4 M3 10h18 M8 14h2 M14 14h2',
        'analytics' => 'M3 3v18h18 M7 17v-5 M12 17V7 M17 17V4',
        'redirects' => 'M4 4v6h10a5 5 0 0 1 0 10H4 M8 16l-4 4 4 4 M16 6l4 4-4 4',
        'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M17 12l2 2 4-4',
        'menus' => 'M4 6h16 M4 12h16 M4 18h16',
        'settings' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1z',
        'sidebar-toggle' => 'M3 4h18v16H3z M9 4v16 M13 9l3 3-3 3',
    ];
    $key = $route === 'sidebar-toggle' ? $route : explode('.', $route)[1];
@endphp
<svg class="cms-nav-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$key] ?? $paths['pages'] }}" /></svg>
