<?php

return [
    'Dashboard' => [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => ['admin.dashboard'], 'permissions' => ['dashboard.view']],
    ],
    'Content' => [
        ['label' => 'Articles', 'route' => 'admin.posts.index', 'active' => ['admin.posts.*'], 'permissions' => ['articles.view']],
        ['label' => 'Categories', 'route' => 'admin.categories.index', 'active' => ['admin.categories.*'], 'permissions' => ['categories.view']],
        ['label' => 'Tags', 'route' => 'admin.tags.index', 'active' => ['admin.tags.*'], 'permissions' => ['tags.view']],
        ['label' => 'Topics', 'route' => 'admin.topics.index', 'active' => ['admin.topics.*'], 'permissions' => ['topics.view']],
        ['label' => 'Authors', 'route' => 'admin.authors.index', 'active' => ['admin.authors.*'], 'permissions' => ['authors.view']],
        ['label' => 'Media', 'route' => 'admin.media.index', 'active' => ['admin.media.*'], 'permissions' => ['media.view']],
        ['label' => 'Videos', 'route' => 'admin.videos.index', 'active' => ['admin.videos.*'], 'permissions' => ['videos.view']],
        ['label' => 'Pages', 'route' => 'admin.pages.index', 'active' => ['admin.pages.*'], 'permissions' => ['pages.view']],
    ],
    'Learning' => [
        ['label' => 'Quizzes', 'route' => 'admin.quizzes.index', 'active' => ['admin.quizzes.*'], 'permissions' => ['quizzes.view']],
        ['label' => 'Learning Paths', 'route' => 'admin.learning-paths.index', 'active' => ['admin.learning-paths.*'], 'permissions' => ['learning-paths.view']],
        ['label' => 'Courses', 'route' => 'admin.courses.index', 'active' => ['admin.courses.*'], 'permissions' => ['courses.view']],
    ],
    'Engagement' => [
        ['label' => 'Q&A', 'route' => 'admin.questions.index', 'active' => ['admin.questions.*'], 'permissions' => ['qa.view']],
    ],
    'Publishing' => [
        ['label' => 'Editorial Calendar', 'route' => 'admin.editorial-calendar', 'active' => ['admin.editorial-calendar'], 'permissions' => ['editorial-calendar.view']],
    ],
    'SEO & Insights' => [
        ['label' => 'Analytics', 'route' => 'admin.analytics', 'active' => ['admin.analytics'], 'permissions' => ['analytics.view']],
        ['label' => 'Redirects', 'route' => 'admin.redirects.index', 'active' => ['admin.redirects.*'], 'permissions' => ['redirects.view']],
    ],
    'Management' => [
        ['label' => 'Users & Access', 'route' => 'admin.users.index', 'active' => ['admin.users.*', 'admin.roles.*'], 'permissions' => ['users.view', 'roles.view']],
        ['label' => 'Menus', 'route' => 'admin.menus.index', 'active' => ['admin.menus.*'], 'permissions' => ['menus.view']],
        ['label' => 'Settings', 'route' => 'admin.settings.edit', 'active' => ['admin.settings.*'], 'permissions' => ['settings.view']],
    ],
];
