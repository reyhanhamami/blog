<?php

use App\Models\Post;
use App\Models\User;

test('focus keyphrase persists on create and loads in the shared edit form', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);

    $this->actingAs($admin)->get(route('admin.posts.create'))
        ->assertOk()
        ->assertSee('SEO &amp; AEO Analysis', false)
        ->assertSee('name="focus_keyphrase"', false);

    $this->post(route('admin.posts.store'), [
        'title' => 'Panduan Prompt AI',
        'slug' => '',
        'focus_keyphrase' => 'prompt AI untuk coding',
        'status' => 'draft',
        'content_type' => 'tutorial',
    ])->assertRedirect();

    $post = Post::where('slug', 'panduan-prompt-ai')->firstOrFail();
    expect($post->focus_keyphrase)->toBe('prompt AI untuk coding');

    $this->get(route('admin.posts.edit', $post))
        ->assertOk()
        ->assertSee('SEO &amp; AEO Analysis', false)
        ->assertSee('value="prompt AI untuk coding"', false);
});

test('existing posts remain editable with an empty focus keyphrase', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin)->post(route('admin.posts.store'), [
        'title' => 'Artikel Lama',
        'slug' => '',
        'status' => 'draft',
        'content_type' => 'article',
    ])->assertRedirect();

    $post = Post::where('slug', 'artikel-lama')->firstOrFail();
    expect($post->focus_keyphrase)->toBeNull();

    $this->get(route('admin.posts.edit', $post))->assertOk();
    $this->patch(route('admin.posts.update', $post), [
        'title' => 'Artikel Lama',
        'slug' => 'artikel-lama',
        'focus_keyphrase' => 'artikel lama',
        'status' => 'draft',
        'content_type' => 'article',
    ])->assertRedirect();
    expect($post->fresh()->focus_keyphrase)->toBe('artikel lama');
});
