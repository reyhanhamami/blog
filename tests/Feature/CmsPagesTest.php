<?php

use App\Models\User;

foreach (['/', '/posts', '/posts/create', '/categories', '/categories/create', '/tags', '/topics', '/authors', '/media', '/pages', '/menus', '/questions', '/videos', '/quizzes', '/learning-paths', '/courses', '/editorial-calendar', '/analytics', '/redirects', '/users', '/settings'] as $path) {
    test('CMS page '.$path.' renders for superadmin', function () use ($path) {
        $user = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($user)->get('/admin'.$path)->assertOk();
    });
}
