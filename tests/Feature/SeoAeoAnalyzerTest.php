<?php

use App\Models\Post;
use App\Models\User;

test('focus keyphrase persists on create and loads in the shared edit form', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);

    $create = $this->actingAs($admin)->get(route('admin.posts.create'))
        ->assertOk()
        ->assertSee('SEO &amp; AEO Analysis', false)
        ->assertSee('name="focus_keyphrase"', false);
    $markup = $create->getContent();
    expect(strpos($markup, 'data-taxonomy-quick-create'))->toBeLessThan(strpos($markup, 'data-seo-analysis'));
    expect(strpos($markup, 'data-seo-analysis'))->toBeLessThan(strpos($markup, 'admin-post-actions'));

    $this->post(route('admin.posts.store'), [
        'title' => 'Panduan Prompt AI',
        'slug' => '',
        'focus_keyphrase' => 'prompt AI untuk coding',
        'status' => 'draft',
        'content_type' => 'tutorial',
    ])->assertRedirect();

    $post = Post::where('slug', 'panduan-prompt-ai')->firstOrFail();
    expect($post->focus_keyphrase)->toBe('prompt AI untuk coding');

    $edit = $this->get(route('admin.posts.edit', $post))
        ->assertOk()
        ->assertSee('SEO &amp; AEO Analysis', false)
        ->assertSee('value="prompt AI untuk coding"', false);
    $markup = $edit->getContent();
    expect(strpos($markup, 'data-taxonomy-quick-create'))->toBeLessThan(strpos($markup, 'data-seo-analysis'));
    expect(strpos($markup, 'data-seo-analysis'))->toBeLessThan(strpos($markup, 'admin-post-actions'));
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

test('shared create and edit forms expose the same article, SEO, AEO, and reference inputs', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin);
    $this->post(route('admin.posts.store'), ['title' => 'Audit Form', 'slug' => '', 'status' => 'draft', 'content_type' => 'article'])->assertRedirect();
    $post = Post::where('slug', 'audit-form')->firstOrFail();
    foreach ([route('admin.posts.create'), route('admin.posts.edit', $post)] as $url) {
        $response = $this->get($url)->assertOk();
        foreach (['title', 'slug', 'excerpt', 'content', 'focus_keyphrase', 'seo_title', 'seo_description', 'og_title', 'og_description', 'featured_image', 'featured_image_alt', 'og_image', 'author_id', 'category_id', 'tags[]', 'topics[]', 'direct_answer', 'key_takeaways', 'summary', 'references[0][title]', 'references[0][url]', 'status', 'scheduled_at'] as $name) {
            $response->assertSee('name="'.$name.'"', false);
        }
    }
});

test('references can be created, updated, and removed from the article form', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin)->post(route('admin.posts.store'), [
        'title' => 'Docker Sources', 'slug' => '', 'status' => 'draft', 'content_type' => 'tutorial', 'references_present' => '1',
        'references' => [['title' => 'Dokumentasi Docker', 'url' => 'https://docs.docker.com/']],
    ])->assertSessionHasNoErrors()->assertRedirect();
    $post = Post::where('slug', 'docker-sources')->firstOrFail();
    $source = $post->sources()->firstOrFail();
    expect($source->url)->toBe('https://docs.docker.com/');
    $this->get(route('admin.posts.edit', $post))->assertSee('value="https://docs.docker.com/"', false);
    $this->patch(route('admin.posts.update', $post), [
        'title' => $post->title, 'slug' => $post->slug, 'status' => 'draft', 'content_type' => 'tutorial', 'references_present' => '1',
        'references' => [['id' => $source->id, 'title' => 'Docker Docs', 'url' => 'https://docs.docker.com/get-started/']],
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($post->sources()->firstOrFail()->title)->toBe('Docker Docs');
    $this->patch(route('admin.posts.update', $post), ['title' => $post->title, 'slug' => $post->slug, 'status' => 'draft', 'content_type' => 'tutorial', 'references_present' => '1'])->assertSessionHasNoErrors()->assertRedirect();
    expect($post->sources()->count())->toBe(0);
});

test('reference URLs reject unsafe schemes and source IDs from another article', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin)->post(route('admin.posts.store'), [
        'title' => 'Unsafe Source', 'slug' => '', 'status' => 'draft', 'content_type' => 'article', 'references_present' => '1',
        'references' => [['title' => 'Unsafe', 'url' => 'javascript:alert(1)']],
    ])->assertSessionHasErrors('references.0.url');
    $this->post(route('admin.posts.store'), ['title' => 'First Source Article', 'slug' => '', 'status' => 'draft', 'content_type' => 'article'])->assertRedirect();
    $this->post(route('admin.posts.store'), ['title' => 'Second Source Article', 'slug' => '', 'status' => 'draft', 'content_type' => 'article'])->assertRedirect();
    $one = Post::where('slug', 'first-source-article')->firstOrFail();
    $two = Post::where('slug', 'second-source-article')->firstOrFail();
    $source = $one->sources()->create(['title' => 'Existing', 'url' => 'https://example.org']);
    $this->patch(route('admin.posts.update', $two), [
        'title' => $two->title, 'slug' => $two->slug, 'status' => 'draft', 'content_type' => 'article', 'references_present' => '1',
        'references' => [['id' => $source->id, 'title' => 'Foreign', 'url' => 'https://example.org']],
    ])->assertSessionHasErrors('references.0.id');
});

test('published article renderer resolves schema author, description, canonical, date, and saved reference', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin)->post(route('admin.posts.store'), [
        'title' => 'Cara Deploy Docker', 'slug' => '', 'excerpt' => 'Pelajari cara deploy Docker dengan aman.',
        'status' => 'published', 'content_type' => 'tutorial', 'references_present' => '1',
        'references' => [['title' => 'Dokumentasi Docker', 'url' => 'https://docs.docker.com/']],
    ])->assertSessionHasNoErrors()->assertRedirect();
    $post = Post::where('slug', 'cara-deploy-docker')->firstOrFail();
    expect($post->published_at)->not->toBeNull();
    $html = $this->get(route('post.show', $post->slug))->assertOk()->getContent();
    expect($html)->toContain('"@type":"Article"', '"description":"Pelajari cara deploy Docker dengan aman."', '"name":"Tim Besofton"', '"datePublished":"'.$post->published_at->toAtomString().'"', '"mainEntityOfPage":"'.url('/'.$post->slug).'"', 'https://docs.docker.com/');
});
